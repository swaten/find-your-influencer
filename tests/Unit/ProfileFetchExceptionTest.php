<?php

namespace Tests\Unit;

use App\Enums\Platform;
use App\Exceptions\ProfileFetchException;
use App\Models\Profile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// pure classification logic - no db/http/app boot needed, a plain in-memory
// Profile instance is enough. deliberately extends PHPUnit's TestCase directly
// (not Tests\TestCase) so this stays a real, fast unit test.
class ProfileFetchExceptionTest extends TestCase
{
    private function profile(): Profile
    {
        $profile = new Profile();
        $profile->platform = Platform::Instagram;
        $profile->username = 'someone';

        return $profile;
    }

    public function test_not_found_is_fatal_not_retriable(): void
    {
        $this->assertFalse(ProfileFetchException::notFound($this->profile())->retriable);
    }

    public function test_unauthorized_is_fatal_not_retriable(): void
    {
        $this->assertFalse(ProfileFetchException::unauthorized($this->profile())->retriable);
    }

    #[DataProvider('retriableStatuses')]
    public function test_server_errors_timeouts_and_rate_limits_are_retriable(int $status): void
    {
        $exception = ProfileFetchException::providerError($this->profile(), $status, 'body');
        $this->assertTrue($exception->retriable, "HTTP {$status} should be retriable");
    }

    public static function retriableStatuses(): array
    {
        return [
            'internal server error' => [500],
            'bad gateway' => [502],
            'service unavailable' => [503],
            'request timeout' => [408],
            'too many requests' => [429],
        ];
    }

    #[DataProvider('fatalStatuses')]
    public function test_client_errors_besides_429_are_fatal_not_retriable(int $status): void
    {
        $exception = ProfileFetchException::providerError($this->profile(), $status, 'body');
        $this->assertFalse($exception->retriable, "HTTP {$status} should not be retriable");
    }

    public static function fatalStatuses(): array
    {
        return [
            'bad request' => [400],
            'not found' => [404],
            'unauthorized' => [401],
            'forbidden' => [403],
            'unprocessable' => [422],
        ];
    }
}
