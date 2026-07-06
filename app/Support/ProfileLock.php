<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

// postgres session-level advisory lock, keyed on a profile id - guarantees only
// one worker actually calls the provider for a given profile at a time, even if
// two queue processes both grab a FetchProfileJob for the same profile at once.
//
// why not Cache::lock() (redis)? a redis lock is a TTL-based mutex - if it expires
// mid-job (slow provider call) a second worker can walk right in, and if it never
// expires it can leak forever if our own release() code doesn't run. a postgres
// advisory lock is tied to the database session itself: if this worker process
// dies for any reason (crash, kill -9, oom), postgres notices the dropped
// connection and releases every advisory lock that session held - automatically,
// with no dependency on our cleanup code running at all.
class ProfileLock
{
    // arbitrary fixed namespace so these locks never collide with an unrelated
    // advisory lock elsewhere in the app using the same small integer as a key
    private const LOCK_CLASS_ID = 726352;

    // non-blocking - returns immediately, never waits on the row
    public static function acquire(int $profileId): bool
    {
        $row = DB::selectOne('select pg_try_advisory_lock(?, ?) as locked', [self::LOCK_CLASS_ID, $profileId]);

        return (bool) $row->locked;
    }

    public static function release(int $profileId): void
    {
        DB::select('select pg_advisory_unlock(?, ?)', [self::LOCK_CLASS_ID, $profileId]);
    }
}
