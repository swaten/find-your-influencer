<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    // which concrete provider each platform uses - 'fake' in tests, real drivers otherwise
    'profile_provider' => [
        'instagram' => env('PROFILE_PROVIDER_INSTAGRAM', 'fake'),
        'youtube' => env('PROFILE_PROVIDER_YOUTUBE', 'fake'),
    ],

    'apify' => [
        'token' => env('APIFY_API_TOKEN'),
        'instagram_actor_id' => env('APIFY_INSTAGRAM_ACTOR_ID', 'apify/instagram-profile-scraper'),
        // youtube also goes through apify (scrapers-hub/youtube-profile-scraper) rather
        // than the official Data API v3 - no google api key needed
        'youtube_actor_id' => env('APIFY_YOUTUBE_ACTOR_ID', 'scrapers-hub/youtube-profile-scraper'),
    ],

    // caps how often FetchProfileJob is allowed to actually call each provider,
    // regardless of how many profiles are queued up - keeps cost/rate bounded at any scale
    'rate_limits' => [
        'instagram' => [
            'capacity' => (int) env('APIFY_RATE_LIMIT_CAPACITY', 5),
            'refill_per_minute' => (int) env('APIFY_RATE_LIMIT_PER_MINUTE', 30),
        ],
        'youtube' => [
            'capacity' => (int) env('YOUTUBE_RATE_LIMIT_CAPACITY', 10),
            'refill_per_minute' => (int) env('YOUTUBE_RATE_LIMIT_PER_MINUTE', 100),
        ],
    ],

    // shared secret per provider for verifying POST /webhooks/{provider} - HMAC-SHA256
    // of the raw request body must match the X-Webhook-Signature header
    'webhooks' => [
        'apify' => env('APIFY_WEBHOOK_SECRET'),
    ],

];
