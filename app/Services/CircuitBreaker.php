<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

// redis-backed circuit breaker, one per platform. closed by default; after enough
// consecutive failures it opens and rejects everything for a cooldown; once the
// cooldown passes exactly one caller gets a half-open "probe" attempt through
class CircuitBreaker
{
    private const FAILURE_THRESHOLD = 10;
    private const COOLDOWN_SECONDS = 120;

    public function __construct(private readonly string $key)
    {
    }

    // true if a call is allowed through right now (circuit closed, or this is the probe)
    public function allowsRequest(): bool
    {
        $script = <<<'LUA'
            local open_until_key = KEYS[1]
            local probing_key = KEYS[2]
            local now = tonumber(ARGV[1])
            local cooldown = tonumber(ARGV[2])

            local open_until = tonumber(redis.call('GET', open_until_key))

            if open_until == nil then
                return 1
            end

            if now < open_until then
                return 0
            end

            -- cooldown elapsed - let exactly one caller through to probe
            local got_slot = redis.call('SET', probing_key, '1', 'NX', 'EX', cooldown)
            if got_slot then
                return 1
            end

            return 0
        LUA;

        $allowed = Redis::eval(
            $script, 2, $this->openUntilKey(), $this->probingKey(), microtime(true), self::COOLDOWN_SECONDS
        );

        return (bool) $allowed;
    }

    // a successful call (including a successful probe) fully resets the breaker
    public function recordSuccess(): void
    {
        Redis::del($this->failuresKey(), $this->openUntilKey(), $this->probingKey());
    }

    // a failure (including a failed probe) counts up, tripping/re-tripping the breaker
    public function recordFailure(): void
    {
        $script = <<<'LUA'
            local failures_key = KEYS[1]
            local open_until_key = KEYS[2]
            local probing_key = KEYS[3]
            local threshold = tonumber(ARGV[1])
            local now = tonumber(ARGV[2])
            local cooldown = tonumber(ARGV[3])

            local failures = redis.call('INCR', failures_key)
            redis.call('EXPIRE', failures_key, cooldown * 10)

            if failures >= threshold then
                redis.call('SET', open_until_key, now + cooldown, 'EX', cooldown + 10)
            end

            redis.call('DEL', probing_key)
        LUA;

        Redis::eval(
            $script, 3, $this->failuresKey(), $this->openUntilKey(), $this->probingKey(),
            self::FAILURE_THRESHOLD, microtime(true), self::COOLDOWN_SECONDS
        );
    }

    private function failuresKey(): string
    {
        return "circuit:{$this->key}:failures";
    }

    private function openUntilKey(): string
    {
        return "circuit:{$this->key}:open_until";
    }

    private function probingKey(): string
    {
        return "circuit:{$this->key}:probing";
    }
}
