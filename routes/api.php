<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\LoginHistoryController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\SupportChatController;
use App\Http\Controllers\Api\TrackingEventController;
use Illuminate\Support\Facades\Route;

// All routes here are mounted under /api/v1 by bootstrap/app.php.
// Anything inside ->middleware('auth:sanctum') requires a Sanctum bearer token.

Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login',    [AuthController::class, 'login']);

// Public property search + show — no auth required (browse-without-login).
Route::get('properties',                [PropertyController::class, 'index']);
Route::get('properties/{property}',     [PropertyController::class, 'show']);

// Public tracking ingest (anonymous or auth-optional) — rate limited.
Route::post('tracking/events', [TrackingEventController::class, 'store'])
    ->middleware('throttle:60,1');

// AI support chat (FR-11). Anonymous chat allowed for marketing-page visitors;
// auth-optional via Sanctum guard. Per-IP rate-limited (FR-11.8: 30/IP/min).
Route::post('support/chat', [SupportChatController::class, 'chat'])
    ->middleware('throttle:30,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me',      [AuthController::class, 'me']);

    Route::get('bookings',               [BookingController::class, 'index']);
    Route::post('bookings',              [BookingController::class, 'store']);
    Route::get('bookings/{booking}',     [BookingController::class, 'show']);
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel']);

    // FR-10.7: user-side login history + activity map
    Route::get('me/login-history', [LoginHistoryController::class, 'mine']);
    Route::get('me/activity-map',  [LoginHistoryController::class, 'activityMap']);
});
