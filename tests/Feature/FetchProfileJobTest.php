<?php

namespace Tests\Feature;

use App\Enums\Platform;
use App\Enums\ProfileStatus;
use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use App\Services\CircuitBreakerRegistry;
use App\Services\ProfileProviderManager;
use App\Services\RateLimiterRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use Throwable;

// exercises FetchProfileJob's handle() directly (Laravel's own recommended way to
// test job logic) against the real InstagramApifyProvider, with Http::fake()
// standing in for the actual network call - so this proves the retry
// classification, rate limiter, and circuit breaker actually change job
// behaviour, not just that the classes exist in isolation.
class FetchProfileJobTest extends TestCase
{
    use RefreshDatabase;

    private function runJob(Profile $profile): void
    {
        (new FetchProfileJob($profile->id))->handle(
            app(ProfileProviderManager::class),
            app(RateLimiterRegistry::class),
            app(CircuitBreakerRegistry::class),
        );
    }

    // ── positive: success path ──────────────────────────────────────────

    public function test_a_successful_fetch_writes_a_snapshot_and_updates_the_profile(): void
    {
        Http::fake([
            'api.apify.com/*' => Http::response([[
                'id' => '123',
                'username' => 'dhruvrathee',
                'fullName' => 'Dhruv Rathee',
                'followersCount' => 5_000_000,
                'followsCount' => 10,
                'postsCount' => 200,
                'profilePicUrl' => 'https://example.test/pic.jpg',
            ]], 200),
        ]);

        $profile = Profile::factory()->create(['platform' => 'instagram', 'username' => 'dhruvrathee', 'status' => 'pending']);

        $this->runJob($profile);

        $profile->refresh();
        $this->assertSame(ProfileStatus::Fetched, $profile->status);
        $this->assertSame(5_000_000, $profile->last_followers_count);
        $this->assertSame(0, $profile->consecutive_failures);
        $this->assertDatabaseCount('profile_snapshots', 1);
    }

    // ── negative: fatal errors fail immediately, no retry ──────────────────

    public function test_a_404_marks_the_profile_failed_and_does_not_throw_for_retry(): void
    {
        Http::fake(['api.apify.com/*' => Http::response([], 404)]);

        $profile = Profile::factory()->create(['platform' => 'instagram', 'status' => 'pending']);

        // fatal path calls $this->fail() and returns - it must not escape as an exception
        $this->runJob($profile);

        $profile->refresh();
        $this->assertSame(ProfileStatus::Failed, $profile->status);
        $this->assertSame(1, $profile->consecutive_failures);
        $this->assertDatabaseCount('profile_snapshots', 0);
    }

    public function test_a_401_from_the_provider_marks_the_profile_failed_and_does_not_throw_for_retry(): void
    {
        Http::fake(['api.apify.com/*' => Http::response(['error' => 'bad token'], 401)]);

        $profile = Profile::factory()->create(['platform' => 'instagram', 'status' => 'pending']);

        $this->runJob($profile);

        $profile->refresh();
        $this->assertSame(ProfileStatus::Failed, $profile->status);
    }

    // ── negative: retriable errors fail this attempt but rethrow so the
    //    queue's own $tries/backoff() picks it up again ─────────────────────

    public function test_a_500_marks_the_profile_failed_and_rethrows_so_the_queue_retries(): void
    {
        Http::fake(['api.apify.com/*' => Http::response('upstream error', 500)]);

        $profile = Profile::factory()->create(['platform' => 'instagram', 'status' => 'pending']);

        $thrown = null;
        try {
            $this->runJob($profile);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'a retriable failure must rethrow so Laravel\'s queue retries it');

        $profile->refresh();
        $this->assertSame(ProfileStatus::Failed, $profile->status);
        $this->assertSame(1, $profile->consecutive_failures);
    }

    // ── rate limiter: an exhausted bucket reschedules instead of calling the provider ──

    public function test_an_exhausted_rate_limit_bucket_reschedules_without_calling_the_provider(): void
    {
        Queue::fake();
        Http::fake(['api.apify.com/*' => Http::response([], 200)]);

        $profile = Profile::factory()->create(['platform' => 'instagram', 'status' => 'pending']);

        // drain the bucket completely before the job ever gets a token
        $capacity = (int) config('services.rate_limits.instagram.capacity', 5);
        $limiter = app(RateLimiterRegistry::class)->for(Platform::Instagram);
        for ($i = 0; $i < $capacity; $i++) {
            $limiter->attempt();
        }

        $this->runJob($profile);

        Http::assertNothingSent();
        Queue::assertPushed(FetchProfileJob::class, fn (FetchProfileJob $job) => $job->profileId === $profile->id && $job->delay !== null);

        $profile->refresh();
        $this->assertSame(ProfileStatus::Pending, $profile->status, 'a throttled job must not flash "fetching" in the UI');
    }

    // ── circuit breaker: an open circuit reschedules without calling the provider ──

    public function test_an_open_circuit_reschedules_without_calling_the_provider(): void
    {
        Queue::fake();
        Http::fake(['api.apify.com/*' => Http::response([], 200)]);

        $profile = Profile::factory()->create(['platform' => 'instagram', 'status' => 'pending']);

        // simulate a breaker that tripped moments ago and hasn't cooled down yet
        Redis::set('circuit:instagram:open_until', microtime(true) + 60);

        $this->runJob($profile);

        Http::assertNothingSent();
        Queue::assertPushed(FetchProfileJob::class, fn (FetchProfileJob $job) => $job->profileId === $profile->id && $job->delay !== null);
    }
}
