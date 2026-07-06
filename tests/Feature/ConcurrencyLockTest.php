<?php

namespace Tests\Feature;

use App\Enums\ProfileStatus;
use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use App\Services\CircuitBreakerRegistry;
use App\Services\ProfileProviderManager;
use App\Services\RateLimiterRegistry;
use App\Support\ProfileLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

// module 6 / module 11's required "automated concurrency test" - proves two
// workers can't both fetch the same profile at once. a genuinely separate
// postgres connection stands in for "a second worker process", since a
// session-level advisory lock is reentrant within the SAME connection (calling
// pg_try_advisory_lock twice from one session would just succeed twice) -
// the only real way to prove mutual exclusion is two distinct sessions.
class ConcurrencyLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // clone the real pgsql connection config under a new name - Laravel opens
        // a brand new PDO connection (a real, separate postgres backend session)
        // for it the first time it's used
        config(['database.connections.pgsql_worker_b' => config('database.connections.pgsql')]);
    }

    protected function tearDown(): void
    {
        DB::purge('pgsql_worker_b');
        parent::tearDown();
    }

    public function test_a_second_session_cannot_acquire_a_lock_the_first_session_still_holds(): void
    {
        $profileId = Profile::factory()->create()->id;
        $workerB = DB::connection('pgsql_worker_b');

        $this->assertTrue(ProfileLock::acquire($profileId), 'worker A should acquire the lock uncontended');

        try {
            $lockedForB = (bool) $workerB->selectOne(
                'select pg_try_advisory_lock(726352, ?) as locked', [$profileId]
            )->locked;

            $this->assertFalse(
                $lockedForB,
                'a second, independent postgres session must not be able to acquire a lock worker A still holds'
            );
        } finally {
            ProfileLock::release($profileId);
        }

        // worker A released it - worker B can now acquire it fresh
        $lockedForBAfterRelease = (bool) $workerB->selectOne(
            'select pg_try_advisory_lock(726352, ?) as locked', [$profileId]
        )->locked;

        $this->assertTrue($lockedForBAfterRelease, 'once worker A releases, another session must be able to acquire it');

        // clean up worker B's own lock so nothing leaks into another test
        $workerB->select('select pg_advisory_unlock(726352, ?)', [$profileId]);
    }

    public function test_fetch_profile_job_reschedules_instead_of_double_fetching_when_another_session_holds_the_lock(): void
    {
        Queue::fake();
        Http::fake(['api.apify.com/*' => Http::response([[
            'id' => '1', 'followersCount' => 1, 'followsCount' => 1, 'postsCount' => 1,
        ]], 200)]);

        $profile = Profile::factory()->create(['status' => 'pending']);
        $workerB = DB::connection('pgsql_worker_b');

        // simulate "another worker is already mid-fetch for this exact profile"
        $held = (bool) $workerB->selectOne(
            'select pg_try_advisory_lock(726352, ?) as locked', [$profile->id]
        )->locked;
        $this->assertTrue($held, 'test setup sanity check: worker B must actually hold the lock for this to prove anything');

        try {
            (new FetchProfileJob($profile->id))->handle(
                app(ProfileProviderManager::class),
                app(RateLimiterRegistry::class),
                app(CircuitBreakerRegistry::class),
            );

            // the guarantee this test exists to prove: no second http call happened
            Http::assertNothingSent();

            Queue::assertPushed(
                FetchProfileJob::class,
                fn (FetchProfileJob $job) => $job->profileId === $profile->id && $job->delay !== null
            );

            $profile->refresh();
            $this->assertSame(
                ProfileStatus::Pending,
                $profile->status,
                'a lock-contended job must not touch status - another worker owns this fetch'
            );
        } finally {
            $workerB->select('select pg_advisory_unlock(726352, ?)', [$profile->id]);
        }
    }
}
