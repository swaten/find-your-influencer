<?php

namespace App\Services;

use App\Enums\Platform;

// one breaker per platform - instagram going down shouldn't trip youtube's circuit
class CircuitBreakerRegistry
{
    public function for(Platform $platform): CircuitBreaker
    {
        return new CircuitBreaker($platform->value);
    }
}
