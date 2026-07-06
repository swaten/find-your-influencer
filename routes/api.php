<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InfluencerController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// public - external callers, verified by HMAC signature instead of session/CSRF
Route::post('/webhooks/{provider}', [WebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/influencers', [InfluencerController::class, 'index']);
    Route::post('/influencers', [InfluencerController::class, 'store']);
    Route::get('/influencers/{profile}', [InfluencerController::class, 'show']);
    Route::delete('/influencers/{profile}', [InfluencerController::class, 'destroy']);
    Route::post('/influencers/{profile}/refresh', [InfluencerController::class, 'refresh']);
});
