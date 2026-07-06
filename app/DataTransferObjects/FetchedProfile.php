<?php

namespace App\DataTransferObjects;

// normalized shape every provider maps its raw api response into
class FetchedProfile
{
    public function __construct(
        public readonly ?string $externalId,
        public readonly ?string $displayName,
        public readonly ?string $avatarUrl,
        public readonly ?int $followersCount,
        public readonly ?int $followingCount,
        public readonly ?int $postsCount,
        public readonly array $rawPayload,
    ) {
    }
}
