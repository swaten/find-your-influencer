<?php

namespace App\Services\Providers;

use App\Contracts\ProfileProvider;
use App\DataTransferObjects\FetchedProfile;
use App\Exceptions\ProfileFetchException;
use App\Models\Profile;
use Illuminate\Support\Facades\Http;

// calls apify/instagram-profile-scraper synchronously and maps its response to our shape
class InstagramApifyProvider implements ProfileProvider
{
    public function fetch(Profile $profile): FetchedProfile
    {
        $token = config('services.apify.token');
        $actorId = config('services.apify.instagram_actor_id');

        // apify's rest api addresses actors as username~actor-name, not username/actor-name
        $endpoint = 'https://api.apify.com/v2/acts/'.str_replace('/', '~', $actorId).'/run-sync-get-dataset-items';

        $response = Http::timeout(60)
            ->withToken($token)
            ->post($endpoint, [
                'usernames' => [$profile->username],
            ]);

        if ($response->status() === 404) {
            throw ProfileFetchException::notFound($profile);
        }

        if (in_array($response->status(), [401, 403])) {
            throw ProfileFetchException::unauthorized($profile);
        }

        if ($response->failed()) {
            throw ProfileFetchException::providerError($profile, $response->status(), $response->body());
        }

        $items = $response->json();

        if (empty($items)) {
            throw ProfileFetchException::notFound($profile);
        }

        $item = $items[0];

        return new FetchedProfile(
            externalId: isset($item['id']) ? (string) $item['id'] : null,
            displayName: $item['fullName'] ?? null,
            avatarUrl: $item['profilePicUrl'] ?? null,
            followersCount: $item['followersCount'] ?? null,
            followingCount: $item['followsCount'] ?? null,
            postsCount: $item['postsCount'] ?? null,
            // only keep the profile-level fields - drop relatedProfiles/latestPosts/igtv,
            // they're huge and we don't need post-level data for this brief
            rawPayload: [
                'id' => $item['id'] ?? null,
                'username' => $item['username'] ?? null,
                'fullName' => $item['fullName'] ?? null,
                'biography' => $item['biography'] ?? null,
                'followersCount' => $item['followersCount'] ?? null,
                'followsCount' => $item['followsCount'] ?? null,
                'postsCount' => $item['postsCount'] ?? null,
                'private' => $item['private'] ?? null,
                'verified' => $item['verified'] ?? null,
                'businessCategoryName' => $item['businessCategoryName'] ?? null,
                'profilePicUrl' => $item['profilePicUrl'] ?? null,
            ],
        );
    }
}
