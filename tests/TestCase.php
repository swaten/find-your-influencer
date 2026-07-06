<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Redis;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // isolated per-test redis db (REDIS_DB=2, see phpunit.xml) flushed before every
        // test - circuit breaker/rate limiter/webhook-nonce keys are never left over
        // from a previous test, and this can never touch real dev/prod redis data
        // since it only runs when APP_ENV=testing.
        if ($this->app->environment('testing')) {
            Redis::connection()->flushdb();
        }
    }
}
