<?php

use App\Http\Controllers\Admin\ContractController as AdminContractController;
use App\Http\Controllers\Admin\UserCertificateController as AdminUserCertificateController;
use App\Http\Controllers\BookingFlowController;
use App\Http\Controllers\Client\ContractController as ClientContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\HostOnboardingController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\MemberEnquiryController;
use App\Http\Controllers\MemberOfferController;
use App\Http\Controllers\NewsletterSubscriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyBrowseController;
use App\Http\Controllers\Webhooks\DocuSignWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::post('/members/enquiry', [MemberEnquiryController::class, 'store'])
    ->name('members.enquiry');

// ---------------------------------------------------------------------------
// Three-audience marketing pages — public, anonymous-friendly. Each surfaces
// a tab in the top nav (Stay → /properties, Become a Host → /become-a-host,
// Members → /members). Stay reuses the existing properties index.
// ---------------------------------------------------------------------------
Route::view('/become-a-host', 'hosts.show')->name('hosts.show');
Route::view('/members',        'members.show')->name('members.show');

// Newsletter signup — distinct from /register (account creation). Captures
// full_name + email + phone for marketing email.
Route::get('/signup',  [NewsletterSubscriptionController::class, 'show'])->name('signup.show');
Route::post('/signup', [NewsletterSubscriptionController::class, 'store'])->name('signup.store');

// Property browse — public surface for visitors. Index supports ?q=, ?city=,
// ?destination= (alias for city), ?min_capacity=, ?max_price=.
Route::get('/properties', [PropertyBrowseController::class, 'index'])->name('properties.index');
Route::get('/properties/{property}', [PropertyBrowseController::class, 'show'])->name('properties.show');

// Booking flow — auth-required so unauthenticated visitors get bounced to
// /login with intended URL, then back to the review page after sign-in.
// terms.current keeps the legal-acceptance gate on the booking funnel too.
Route::middleware(['auth', 'terms.current'])->group(function () {
    Route::get('/account/bookings',            [BookingFlowController::class, 'index'])->name('bookings.index');
    Route::get('/properties/{property}/book',  [BookingFlowController::class, 'review'])->name('bookings.review');
    Route::post('/properties/{property}/book', [BookingFlowController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}',          [BookingFlowController::class, 'show'])->name('bookings.show');
    Route::get('/bookings/{booking}/pay',      [BookingFlowController::class, 'pay'])->name('bookings.pay');
    Route::get('/bookings/{booking}/cancel',   [BookingFlowController::class, 'cancelForm'])->name('bookings.cancel.form');
    Route::post('/bookings/{booking}/cancel',  [BookingFlowController::class, 'cancel'])->name('bookings.cancel');

    // Member offers — accept/decline from the member dashboard. Controller
    // verifies the offer's member_user_id matches the current user and that
    // status is still Pending.
    Route::post('/account/offers/{offer}/accept',  [MemberOfferController::class, 'accept'])->name('member.offers.accept');
    Route::post('/account/offers/{offer}/decline', [MemberOfferController::class, 'decline'])->name('member.offers.decline');

    // Stripe Connect host onboarding (FR-5.x). All routes auth + terms.current.
    Route::get('/host/onboarding',          [HostOnboardingController::class, 'index'])->name('host.onboarding.index');
    Route::post('/host/onboarding',         [HostOnboardingController::class, 'start'])->name('host.onboarding.start');
    Route::get('/host/onboarding/refresh',  [HostOnboardingController::class, 'refresh'])->name('host.onboarding.refresh');
    Route::get('/host/onboarding/return',   [HostOnboardingController::class, 'return'])->name('host.onboarding.return');
});

// ---------------------------------------------------------------------------
// Help center (FR-11.5). Public, anonymous-friendly. Search lives at
// /help/search (JSON) — used by the help page's live search and by the
// support chat agent's search_help_articles tool. /help/{slug} is wildcard
// after /help/search so the literal route wins.
// ---------------------------------------------------------------------------
Route::get('/help', [HelpController::class, 'index'])->name('help.index');
Route::get('/help/search', [HelpController::class, 'search'])->name('help.search');
Route::get('/help/{slug}', [HelpController::class, 'show'])->name('help.show');

// ---------------------------------------------------------------------------
// Legal docs (FR-13). Public, anonymous-friendly. The /legal/versions JSON
// endpoint exposes which version+hash is currently in force per kind so
// auditors and the chat agent can reconcile against terms_versions.
// ---------------------------------------------------------------------------
Route::get('/legal/tos',              [LegalController::class, 'tos'])->name('legal.tos');
Route::get('/legal/privacy',          [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/legal/chargeback',       [LegalController::class, 'chargeback'])->name('legal.chargeback');
Route::get('/legal/member-agreement', [LegalController::class, 'memberAgreement'])->name('legal.member-agreement');
Route::get('/legal/versions',         [LegalController::class, 'versions'])->name('legal.versions');

Route::middleware('auth')->group(function () {
    Route::get('/legal/review-and-accept',  [LegalController::class, 'reviewAndAccept'])->name('legal.review-and-accept');
    Route::post('/legal/review-and-accept', [LegalController::class, 'acceptCurrent'])->name('legal.review-and-accept.submit');
});

// Deeper health check than Laravel 11's built-in /up. Pings DB and Redis
// and returns 503 if either is down — useful as a strict readiness probe
// and for external monitoring dashboards.
Route::get('/health', HealthController::class)->name('health');

Route::get('/dashboard', [DashboardController::class, 'show'])
    ->middleware(['auth', 'verified', 'terms.current'])
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
        // Admin user management (list / create / show / edit / deactivate / reactivate).
        // Every state-changing action writes to admin_audit_logs via AdminAuditLogService.
        Route::get('users',                       [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
        Route::get('users/create',                [\App\Http\Controllers\Admin\UserController::class, 'create'])->name('users.create');
        Route::post('users',                      [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}',                [\App\Http\Controllers\Admin\UserController::class, 'show'])->name('users.show');
        Route::get('users/{user}/edit',           [\App\Http\Controllers\Admin\UserController::class, 'edit'])->name('users.edit');
        Route::patch('users/{user}',              [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/deactivate',    [\App\Http\Controllers\Admin\UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('users/{user}/reactivate',    [\App\Http\Controllers\Admin\UserController::class, 'reactivate'])->name('users.reactivate');

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
