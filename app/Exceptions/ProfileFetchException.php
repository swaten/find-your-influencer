<?php

namespace App\Exceptions;

use App\Models\Profile;
use RuntimeException;

// thrown by a ProfileProvider when a fetch can't complete - $retriable tells the job
// whether to let normal retry/backoff run (5xx/timeout/429) or fail immediately (404/401)
class ProfileFetchException extends RuntimeException
{
    public bool $retriable = true;

    public static function notFound(Profile $profile): self
    {
        $exception = new self("Profile [{$profile->username}] not found on {$profile->platform->value}");
        $exception->retriable = false;

        return $exception;
    }

    public static function unauthorized(Profile $profile): self
    {
        $exception = new self(
            "Provider rejected credentials fetching [{$profile->username}] - check the API token/key"
        );
        $exception->retriable = false;

        return $exception;
    }

    public static function providerError(Profile $profile, int $status, string $body): self
    {
        $exception = new self(
            "Provider error fetching [{$profile->username}]: HTTP {$status} - ".str($body)->limit(300)
        );

        // 5xx, request timeouts, and provider-side rate limiting are worth retrying -
        // everything else (400s besides 429) is treated as fatal
        $exception->retriable = $status >= 500 || in_array($status, [408, 429]);

        return $exception;
    }
}
