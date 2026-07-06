<?php

namespace App\Services\Providers;

use App\Contracts\ProfileProvider;
use App\DataTransferObjects\FetchedProfile;
use App\Models\Profile;

// deterministic stand-in for tests and local dev - never makes a real network call
class FakeProfileProvider implements ProfileProvider
{
    public function fetch(Profile $profile): FetchedProfile
    {
        return new FetchedProfile(
            externalId: 'fake-'.$profile->id,
            displayName: ucfirst($profile->username),
            avatarUrl: 'https://example.test/avatar.jpg',
            followersCount: 1000,
            followingCount: 100,
            postsCount: 50,
            rawPayload: ['fake' => true, 'username' => $profile->username],
        );
    }
}
