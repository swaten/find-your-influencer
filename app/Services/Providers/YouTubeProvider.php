<?php

namespace App\Services\Providers;

use App\Contracts\ProfileProvider;
use App\DataTransferObjects\FetchedProfile;
use App\Exceptions\ProfileFetchException;
use App\Models\Profile;
use Illuminate\Support\Facades\Http;

// calls scrapers-hub/youtube-profile-scraper synchronously and maps its response
// to our shape - same apify run-sync pattern as InstagramApifyProvider, just a
// different actor + input schema (channel urls, not usernames) and output fields
class YouTubeProvider implements ProfileProvider
{
    public function fetch(Profile $profile): FetchedProfile
    {
        $token = config('services.apify.token');
        $actorId = config('services.apify.youtube_actor_id');

        $endpoint = 'https://api.apify.com/v2/acts/'.str_replace('/', '~', $actorId).'/run-sync-get-dataset-items';

        // profile->username is stored without the @ - the actor wants a full channel url
        $channelUrl = 'https://www.youtube.com/@'.ltrim($profile->username, '@').'/about';

        $response = Http::timeout(60)
            ->withToken($token)
            ->post($endpoint, [
                'startUrls' => [['url' => $channelUrl]],
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
            externalId: isset($item['id']) ? (string) $item['id'] : (isset($item['identifier']) ? (string) $item['identifier'] : null),
            displayName: $item['name'] ?? null,
            avatarUrl: $item['avatar'] ?? $item['thumbnail'] ?? null,
            followersCount: $item['subscribers'] ?? null,
            // channels don't have a "following" concept the way profiles do
            followingCount: null,
            postsCount: $item['videos_count'] ?? null,
            rawPayload: [
                'id' => $item['id'] ?? null,
                'identifier' => $item['identifier'] ?? null,
                'url' => $item['url'] ?? null,
                'handle' => $item['handle'] ?? null,
                'name' => $item['name'] ?? null,
                'subscribers' => $item['subscribers'] ?? null,
                'videos_count' => $item['videos_count'] ?? null,
                'views' => $item['views'] ?? null,
                'created_date' => $item['created_date'] ?? null,
                'description' => $item['Description'] ?? $item['description'] ?? null,
            ],
        );
    }
}
