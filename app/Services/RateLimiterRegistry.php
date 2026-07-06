<?php

namespace App\Services;

use App\Enums\Platform;

// hands out a rate limiter per platform - keeps the per-platform bucket size/refill
// config-driven so it can be tuned without touching FetchProfileJob
class RateLimiterRegistry
{
    public function for(Platform $platform): TokenBucketRateLimiter
    {
        $config = config("services.rate_limits.{$platform->value}", [
            'capacity' => 5,
            'refill_per_minute' => 30,
        ]);

        return new TokenBucketRateLimiter(
            key: "rate_limit:{$platform->value}",
            capacity: (int) $config['capacity'],
            refillPerSecond: $config['refill_per_minute'] / 60,
        );
    }
}
