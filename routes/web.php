<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

// checks the three things that actually matter for this app: can we reach postgres,
// can we reach redis (queue/cache/rate-limits/circuit-breaker all depend on it), and
// has a job actually run recently (proves the queue worker is alive, not just the db/redis)
Route::get('/healthz', function () {
    $checks = ['database' => false, 'redis' => false, 'queue_recent_activity' => false];

    try {
        DB::select('select 1');
        $checks['database'] = true;
    } catch (\Throwable $e) {
        //
    }

    try {
        $checks['redis'] = (bool) Redis::ping();
    } catch (\Throwable $e) {
        //
    }

    $lastRun = Redis::get('healthz:last_job_run_at');
    $checks['queue_recent_activity'] = $lastRun && (time() - (int) $lastRun) < 3600;

    $healthy = ! in_array(false, $checks, true);

    return response()->json(['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks], $healthy ? 200 : 503);
});

// single entry point - React Router owns every client-side path from here
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
