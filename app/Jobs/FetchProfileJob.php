<?php

namespace App\Jobs;

use App\Enums\ProfileStatus;
use App\Exceptions\ProfileFetchException;
use App\Models\Profile;
use App\Models\ProfileSnapshot;
use App\Services\CircuitBreakerRegistry;
use App\Services\ProfileProviderManager;
use App\Services\RateLimiterRegistry;
use App\Support\ProfileLock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

// moves a profile pending -> fetching -> fetched/failed - dispatch this, never call a provider directly
class FetchProfileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    // how long to wait before trying again when throttled/circuit-open - neither
    // counts against $tries since neither is a real failure of this attempt
    private const RATE_LIMIT_BACKOFF_SECONDS = 5;

    private const CIRCUIT_OPEN_BACKOFF_SECONDS = 30;

    // another worker already holds this profile's advisory lock - back off briefly
    // and let it finish rather than piling on (this is not a real failure either)
    private const LOCK_CONTENDED_BACKOFF_SECONDS = 5;

    // redis key the /healthz endpoint reads to prove the queue is actually alive
    private const HEALTHZ_HEARTBEAT_KEY = 'healthz:last_job_run_at';

    public function __construct(public int $profileId)
    {
    }

    // fixed backoff between real (retriable) failures - separate from the throttle/breaker delays above
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(
        ProfileProviderManager $providers,
        RateLimiterRegistry $limiters,
        CircuitBreakerRegistry $breakers,
    ): void {
        $startedAt = microtime(true);
        $profile = Profile::query()->find($this->profileId);

        if (! $profile) {
            return;
        }

        // concurrency guard (brief module 6): if another worker is already fetching
        // this exact profile, don't pile on with a second HTTP call - back off and
        // let it finish. session-scoped, so a crashed worker releases it for free.
        if (! ProfileLock::acquire($profile->id)) {
            $this->logRun('lock_contended', $profile, $startedAt);
            self::dispatch($this->profileId)->delay(now()->addSeconds(self::LOCK_CONTENDED_BACKOFF_SECONDS));

            return;
        }

        try {
            $breaker = $breakers->for($profile->platform);

            // circuit open - the provider's been failing hard, don't pile onto it
            if (! $breaker->allowsRequest()) {
                $this->logRun('circuit_open', $profile, $startedAt);
                self::dispatch($this->profileId)->delay(now()->addSeconds(self::CIRCUIT_OPEN_BACKOFF_SECONDS));

                return;
            }

            // checked before touching status - a throttled job shouldn't flash "fetching"
            // in the UI, and a big stale backlog should drain gradually, not all at once
            if (! $limiters->for($profile->platform)->attempt()) {
                $this->logRun('rate_limited', $profile, $startedAt);
                self::dispatch($this->profileId)->delay(now()->addSeconds(self::RATE_LIMIT_BACKOFF_SECONDS));

                return;
            }

            $profile->update([
                'status' => ProfileStatus::Fetching,
                'last_fetch_attempted_at' => now(),
            ]);

            try {
                $result = $providers->driverFor($profile->platform)->fetch($profile);
                $breaker->recordSuccess();

                DB::transaction(function () use ($profile, $result) {
                    ProfileSnapshot::create([
                        'profile_id' => $profile->id,
                        'provider' => $profile->platform->value,
                        'followers_count' => $result->followersCount,
                        'following_count' => $result->followingCount,
                        'posts_count' => $result->postsCount,
                        'raw_payload' => $result->rawPayload,
                        'fetched_at' => now(),
                    ]);

                    $profile->update([
                        'external_id' => $result->externalId ?? $profile->external_id,
                        'display_name' => $result->displayName ?? $profile->display_name,
                        'avatar_url' => $result->avatarUrl ?? $profile->avatar_url,
                        'status' => ProfileStatus::Fetched,
                        'last_fetched_at' => now(),
                        'last_error' => null,
                        'consecutive_failures' => 0,
                        'last_followers_count' => $result->followersCount,
                        'last_following_count' => $result->followingCount,
                        'last_posts_count' => $result->postsCount,
                    ]);
                });

                $this->logRun('success', $profile, $startedAt);
            } catch (Throwable $e) {
                $breaker->recordFailure();

                $profile->update([
                    'status' => ProfileStatus::Failed,
                    'last_error' => str($e->getMessage())->limit(500)->toString(),
                    'consecutive_failures' => $profile->consecutive_failures + 1,
                ]);

                $retriable = ! ($e instanceof ProfileFetchException) || $e->retriable;
                $this->logRun($retriable ? 'failed_retriable' : 'failed_fatal', $profile, $startedAt, $e->getMessage());

                // fatal errors (404/401/validation) aren't worth retrying - stop instead of burning attempts
                if (! $retriable) {
                    $this->fail($e);

                    return;
                }

                throw $e;
            }
        } finally {
            // always released, on every return path above and even if an exception
            // escapes - this is what makes the lock safe to hold across the whole
            // fetch instead of just around the HTTP call itself
            ProfileLock::release($profile->id);
        }
    }

    // one structured JSON line per run (config/logging.php's 'jobs' channel), plus a
    // redis heartbeat /healthz can check to prove the queue is actually processing work
    private function logRun(string $outcome, Profile $profile, float $startedAt, ?string $error = null): void
    {
        Redis::set(self::HEALTHZ_HEARTBEAT_KEY, time());

        Log::channel('jobs')->info('profile_fetch_run', [
            'profile_id' => $profile->id,
            'platform' => $profile->platform->value,
            'username' => $profile->username,
            'outcome' => $outcome,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'attempt' => $this->attempts(),
            'error' => $error,
        ]);
    }
}
