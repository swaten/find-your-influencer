<?php

namespace App\Services;

use App\Contracts\ProfileProvider;
use App\Enums\Platform;
use App\Services\Providers\FakeProfileProvider;
use App\Services\Providers\InstagramApifyProvider;
use App\Services\Providers\YouTubeProvider;
use InvalidArgumentException;

// picks the right provider implementation for a platform, driven by config so
// tests can swap in 'fake' without touching any calling code
class ProfileProviderManager
{
    public function driverFor(Platform $platform): ProfileProvider
    {
        $driver = config("services.profile_provider.{$platform->value}", 'fake');

        return match ($driver) {
            'apify' => app(InstagramApifyProvider::class),
            'youtube' => app(YouTubeProvider::class),
            'fake' => app(FakeProfileProvider::class),
            default => throw new InvalidArgumentException("Unknown profile provider driver [{$driver}]"),
        };
    }
}
