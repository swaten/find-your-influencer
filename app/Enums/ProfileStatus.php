<?php

namespace App\Enums;

// the fetch state machine a profile moves through every refresh cycle
enum ProfileStatus: string
{
    case Pending = 'pending';
    case Fetching = 'fetching';
    case Fetched = 'fetched';
    case Failed = 'failed';
}
