<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PropertyController;
use Illuminate\Support\Facades\Route;

// All routes here are mounted under /api/v1 by bootstrap/app.php.
// Anything inside ->middleware('auth:sanctum') requires a Sanctum bearer token.

Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login',    [AuthController::class, 'login']);

// Public property search + show — no auth required (browse-without-login).
Route::get('properties',                [PropertyController::class, 'index']);
Route::get('properties/{property}',     [PropertyController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me',      [AuthController::class, 'me']);

    Route::get('bookings',               [BookingController::class, 'index']);
    Route::post('bookings',              [BookingController::class, 'store']);
    Route::get('bookings/{booking}',     [BookingController::class, 'show']);
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel']);
});
