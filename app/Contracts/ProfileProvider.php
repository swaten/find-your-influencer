<?php

namespace App\Contracts;

use App\DataTransferObjects\FetchedProfile;
use App\Models\Profile;

// every platform (instagram, youtube, fake) implements this the same way
interface ProfileProvider
{
    public function fetch(Profile $profile): FetchedProfile;
}
