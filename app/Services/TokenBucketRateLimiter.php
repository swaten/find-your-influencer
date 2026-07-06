<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

// classic token bucket, backed by one redis hash per key so concurrent queue
// workers all draw from the same shared pool - the lua script makes read+consume
// atomic so two workers can't both grab "the last token"
class TokenBucketRateLimiter
{
    public function __construct(
        private readonly string $key,
        private readonly int $capacity,
        private readonly float $refillPerSecond,
    ) {
    }

    // tries to take one token - true if allowed to proceed, false if the bucket is empty
    public function attempt(): bool
    {
        $script = <<<'LUA'
            local key = KEYS[1]
            local capacity = tonumber(ARGV[1])
            local refill_per_second = tonumber(ARGV[2])
            local now = tonumber(ARGV[3])

            local bucket = redis.call('HMGET', key, 'tokens', 'timestamp')
            local tokens = tonumber(bucket[1])
            local timestamp = tonumber(bucket[2])

            if tokens == nil then
                tokens = capacity
                timestamp = now
            end

            local elapsed = math.max(0, now - timestamp)
            tokens = math.min(capacity, tokens + (elapsed * refill_per_second))

            local allowed = 0
            if tokens >= 1 then
                tokens = tokens - 1
                allowed = 1
            end

            redis.call('HMSET', key, 'tokens', tokens, 'timestamp', now)
            redis.call('EXPIRE', key, 3600)

            return allowed
        LUA;

        $allowed = Redis::eval($script, 1, $this->key, $this->capacity, $this->refillPerSecond, microtime(true));

        return (bool) $allowed;
    }
}
