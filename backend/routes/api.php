<?php

use App\Http\Controllers\Api\AdminAuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\MembersEnquiryController;
use App\Http\Controllers\Api\PropertyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1/*
|--------------------------------------------------------------------------
| Public (no auth):     register, login, browse properties, submit enquiry
| Authenticated:        user info, bookings, messages, host actions
| Admin-only:           v1/admin/* (members enquiries, moderation, payouts)
*/

Route::prefix('v1')->group(function () {

    // ─── Public ─────────────────────────────────────────────────
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/auth/login',    [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::get('/properties',              [PropertyController::class, 'index']);
    Route::get('/properties/{slug}',       [PropertyController::class, 'show']);
    Route::get('/properties/{slug}/quote', [PropertyController::class, 'quote']);

    // Members Enquiries — public submit, throttled per FR-9.9 (10 / IP / hour)
    Route::post('/members-enquiries', [MembersEnquiryController::class, 'store'])
        ->middleware('throttle:10,60');

    // ─── Authenticated ──────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me',      [AuthController::class, 'me']);

        // Bookings
        Route::get('/bookings',                [BookingController::class, 'index']);
        Route::post('/bookings',               [BookingController::class, 'store']);
        Route::get('/bookings/{code}',         [BookingController::class, 'show']);
        Route::post('/bookings/{code}/pay',    [BookingController::class, 'pay']);
        Route::post('/bookings/{code}/cancel', [BookingController::class, 'cancel']);

        // Messaging — TODO
        // Host    — TODO

        // ─── Admin (requires admin role; middleware enforced in app) ─────
        Route::middleware('admin')->prefix('admin')->group(function () {

            // Members Enquiries queue
            Route::get('/members-enquiries',                   [MembersEnquiryController::class, 'index']);
            Route::get('/members-enquiries/stats',             [MembersEnquiryController::class, 'stats']);
            Route::get('/members-enquiries/export',            [MembersEnquiryController::class, 'export']);
            Route::get('/members-enquiries/{enquiry}',         [MembersEnquiryController::class, 'show']);
            Route::get('/members-enquiries/{enquiry}/audit',   [AdminAuditLogController::class, 'forMembersEnquiry']);
            Route::patch('/members-enquiries/{enquiry}',       [MembersEnquiryController::class, 'update']);
            Route::post('/members-enquiries/{enquiry}/assign', [MembersEnquiryController::class, 'assign']);

            // Audit log
            Route::get('/audit-logs',                          [AdminAuditLogController::class, 'index']);
        });
    });
});

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'time'   => now()->toIso8601String(),
]));
