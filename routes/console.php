<?php

use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// brief spec: check every 10 min, enqueue anything stale >1h - checking cadence
// and staleness window are different numbers, don't conflate them
Schedule::call(function () {
    Profile::query()
        ->where('status', '!=', 'fetching')
        ->where(function ($query) {
            $query->whereNull('last_fetched_at')
                ->orWhere('last_fetched_at', '<', now()->subHour());
        })
        ->pluck('id')
        ->each(fn (int $id) => FetchProfileJob::dispatch($id));
})->everyTenMinutes()->name('refresh-stale-profiles')->withoutOverlapping();
