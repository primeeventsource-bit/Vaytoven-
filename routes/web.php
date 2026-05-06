<?php

use App\Http\Controllers\Admin\ContractController as AdminContractController;
use App\Http\Controllers\Admin\UserCertificateController as AdminUserCertificateController;
use App\Http\Controllers\Client\ContractController as ClientContractController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MemberEnquiryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Webhooks\DocuSignWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::post('/members/enquiry', [MemberEnquiryController::class, 'store'])
    ->name('members.enquiry');

// Deeper health check than Laravel 11's built-in /up. Pings DB and Redis
// and returns 503 if either is down — useful as a strict readiness probe
// and for external monitoring dashboards.
Route::get('/health', HealthController::class)->name('health');

Route::view('/dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// ---------------------------------------------------------------------------
// DocuSign integration
// ---------------------------------------------------------------------------
//
// Admin contract management — gated on the `admin` middleware (EnsureAdmin),
// which checks the user's role enum is `admin` or `super_admin`.
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('contracts',                  [AdminContractController::class, 'index'])->name('contracts.index');
        Route::get('contracts/create',           [AdminContractController::class, 'create'])->name('contracts.create');
        Route::post('contracts',                 [AdminContractController::class, 'store'])->name('contracts.store');
        Route::get('contracts/{contract}',       [AdminContractController::class, 'show'])->name('contracts.show');
        Route::get('contracts/{contract}/signed.pdf',      [AdminContractController::class, 'downloadSigned'])->name('contracts.download.signed');
        Route::get('contracts/{contract}/certificate.pdf', [AdminContractController::class, 'downloadCertificate'])->name('contracts.download.certificate');
        Route::post('contracts/{contract}/void', [AdminContractController::class, 'void'])->name('contracts.void');

        // FR-10.13: per-user login history + Service Usage Confirmation Certificate.
        // Used by admins responding to chargebacks.
        Route::get('users/{user}/login-history',  [AdminUserCertificateController::class, 'loginHistory'])->name('users.login-history');
        Route::get('users/{user}/certificate.pdf', [AdminUserCertificateController::class, 'certificate'])->name('users.certificate');
    });

// Client-facing contract dashboard. Mounted at /account/contracts.
Route::middleware(['auth'])
    ->prefix('account')
    ->name('client.')
    ->group(function () {
        Route::get('contracts',                       [ClientContractController::class, 'index'])->name('contracts.index');
        Route::get('contracts/{contract}',            [ClientContractController::class, 'show'])->name('contracts.show');
        Route::get('contracts/{contract}/sign',       [ClientContractController::class, 'sign'])->name('contracts.sign');
        Route::get('contracts/{contract}/signed.pdf', [ClientContractController::class, 'downloadSigned'])->name('contracts.download');
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Inbound DocuSign Connect webhook. CSRF-excluded via bootstrap/app.php;
// authenticated via HMAC signature (WebhookVerifier).
Route::post('/webhooks/docusign', DocuSignWebhookController::class)
    ->name('webhooks.docusign');

// Inbound Stripe webhooks. CSRF-excluded via bootstrap/app.php;
// authenticated via Stripe-Signature header (StripeWebhookSignatureVerifier).
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe');

require __DIR__.'/auth.php';
