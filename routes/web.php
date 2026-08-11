<?php

use App\Http\Controllers\Admin\ConfigCollectionController;
use App\Http\Controllers\Admin\ContractController as AdminContractController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\FeatureFlagController;
use App\Http\Controllers\Admin\InboxController;
use App\Http\Controllers\Admin\OfferController as AdminOfferController;
use App\Http\Controllers\Admin\PaymentProcessorController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ServiceFeeController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserCertificateController as AdminUserCertificateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\BookingFlowController;
use App\Http\Controllers\CareersController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Client\ContractController as ClientContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\HostOnboardingController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\MemberEnquiryController;
use App\Http\Controllers\MemberOfferController;
use App\Http\Controllers\NewsletterSubscriptionController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\PressController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyBrowseController;
use App\Http\Controllers\SitePageController;
use App\Http\Controllers\TripSupportController;
use App\Http\Controllers\Webhooks\DocuSignWebhookController;
use App\Http\Controllers\Webhooks\NmiWebhookController;
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
Route::view('/members', 'members.show')->name('members.show');

// ---------------------------------------------------------------------------
// Company + resource pages. These replaced the footer's `href="#"` links, and
// each one is backed by real data rather than being a static page occupying a
// URL: /destinations aggregates published listings, /host-resources renders
// help articles, /careers and /press render operator-published records and
// show an honest empty state when there are none, and /contact and
// /trip-support persist submissions with a reference the customer can quote.
//
// Every footer link points at one of these by NAME (partials/site-footer), so
// renaming a route breaks the build instead of producing a dead link.
// ---------------------------------------------------------------------------
Route::get('/destinations', [SitePageController::class, 'destinations'])->name('destinations.index');
Route::get('/mobile-app', [SitePageController::class, 'mobileApp'])->name('mobile-app');
Route::get('/about', [SitePageController::class, 'about'])->name('about');
Route::get('/host-resources', [SitePageController::class, 'hostResources'])->name('host-resources.index');
Route::get('/earnings-calculator', [SitePageController::class, 'earningsCalculator'])->name('earnings-calculator');

Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:10,1')->name('contact.store');

Route::get('/trip-support', [TripSupportController::class, 'show'])->name('trip-support.show');
Route::post('/trip-support', [TripSupportController::class, 'store'])
    ->middleware('throttle:10,1')->name('trip-support.store');

// Literal /careers routes must precede the {opening} wildcard.
Route::get('/careers', [CareersController::class, 'index'])->name('careers.index');
Route::get('/careers/{opening}', [CareersController::class, 'show'])->name('careers.show');
Route::post('/careers/{opening}/apply', [CareersController::class, 'apply'])
    ->middleware('throttle:6,1')->name('careers.apply');

Route::get('/press', [PressController::class, 'index'])->name('press.index');
Route::get('/press/{release}', [PressController::class, 'show'])->name('press.show');

// Newsletter signup — distinct from /register (account creation). Captures
// full_name + email + phone for marketing email.
Route::get('/signup', [NewsletterSubscriptionController::class, 'show'])->name('signup.show');
Route::post('/signup', [NewsletterSubscriptionController::class, 'store'])->name('signup.store');

// Property browse — public surface for visitors. Index supports ?q=, ?city=,
// ?destination= (alias for city), ?min_capacity=, ?max_price=.
Route::get('/properties', [PropertyBrowseController::class, 'index'])->name('properties.index');
Route::get('/properties/{property}', [PropertyBrowseController::class, 'show'])->name('properties.show');

// Booking flow — auth-required so unauthenticated visitors get bounced to
// /login with intended URL, then back to the review page after sign-in.
// terms.current keeps the legal-acceptance gate on the booking funnel too.
Route::middleware(['auth', 'terms.current'])->group(function () {
    Route::get('/account/bookings', [BookingFlowController::class, 'index'])->name('bookings.index');
    Route::get('/properties/{property}/book', [BookingFlowController::class, 'review'])->name('bookings.review');
    Route::post('/properties/{property}/book', [BookingFlowController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}', [BookingFlowController::class, 'show'])->name('bookings.show');
    Route::get('/bookings/{booking}/pay', [BookingFlowController::class, 'pay'])->name('bookings.pay');
    Route::post('/bookings/{booking}/pay', [BookingFlowController::class, 'processPayment'])->name('bookings.pay.process');
    Route::get('/bookings/{booking}/cancel', [BookingFlowController::class, 'cancelForm'])->name('bookings.cancel.form');
    Route::post('/bookings/{booking}/cancel', [BookingFlowController::class, 'cancel'])->name('bookings.cancel');

    // Member offers — accept/decline from the member dashboard. Controller
    // verifies the offer's member_user_id matches the current user and that
    // status is still Pending.
    Route::post('/account/offers/{offer}/accept', [MemberOfferController::class, 'accept'])->name('member.offers.accept');
    Route::post('/account/offers/{offer}/decline', [MemberOfferController::class, 'decline'])->name('member.offers.decline');

    // Buyer inquiries and offers on listings. A buyer submits against a
    // listing; the listing OWNER reviews them on /account/listing-offers and
    // responds there. Owner scoping is by properties.host_id — see
    // MemberOffer::scopeForListingsOwnedBy.
    Route::post('/properties/{property}/offers', [OfferController::class, 'store'])->name('offers.store');
    Route::get('/account/listing-offers', [OfferController::class, 'index'])->name('offers.index');
    Route::post('/account/listing-offers/{offer}/accept', [OfferController::class, 'accept'])->name('offers.accept');
    Route::post('/account/listing-offers/{offer}/decline', [OfferController::class, 'decline'])->name('offers.decline');

    // Host payout enrollment (FR-5.x) — platform-managed since the NMI
    // migration; ops verifies and flips status from the admin console.
    Route::get('/host/onboarding', [HostOnboardingController::class, 'index'])->name('host.onboarding.index');
    Route::post('/host/onboarding', [HostOnboardingController::class, 'start'])->name('host.onboarding.start');
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
Route::get('/legal/tos', [LegalController::class, 'tos'])->name('legal.tos');
Route::get('/legal/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/legal/member-agreement', [LegalController::class, 'memberAgreement'])->name('legal.member-agreement');
Route::get('/legal/versions', [LegalController::class, 'versions'])->name('legal.versions');

Route::middleware('auth')->group(function () {
    Route::get('/legal/review-and-accept', [LegalController::class, 'reviewAndAccept'])->name('legal.review-and-accept');
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
// ---------------------------------------------------------------------------
// Admin portal. Gated per action on granular RBAC permissions
// (App\Support\PermissionCatalog) rather than on a single binary admin flag,
// so a custom role can be given exactly one module.
//
// EVERY route in this group MUST carry a `permission:` middleware — the outer
// group only proves authentication. A route added without one is reachable by
// any signed-in user. RbacPermissionCoverageTest fails the build if that
// happens.
//
// While RBAC is unseeded on an environment, `permission:` falls back to the
// legacy "is this user an admin" check, so this group behaves exactly as it
// did before until RbacSeeder runs. See App\Models\Role::configured().
// ---------------------------------------------------------------------------
Route::middleware(['auth'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Admin user management (list / create / show / edit / deactivate / reactivate).
        // Every state-changing action writes to admin_audit_logs via AdminAuditLogService.
        Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
        Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
        Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:users.view')->name('users.show');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.edit')->name('users.edit');
        Route::patch('users/{user}', [UserController::class, 'update'])->middleware('permission:users.edit')->name('users.update');
        Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->middleware('permission:users.deactivate')->name('users.deactivate');
        Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->middleware('permission:users.deactivate')->name('users.reactivate');

        // Roles & Permissions. Creating roles is deliberately narrower than
        // creating users — see PermissionCatalog and RbacSeeder.
        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.edit')->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit')->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');

        // Cross-platform inquiry/offer register. Read-only: responding is the
        // listing owner's action and lives on the owner dashboard.
        Route::get('offers', [AdminOfferController::class, 'index'])->middleware('permission:offers.view')->name('offers.index');

        // Hosting → Service Fees. Every percentage a booking charges comes
        // from a row here; nothing is hard-coded in the application.
        Route::get('hosting/service-fees', [ServiceFeeController::class, 'index'])
            ->middleware('permission:billing.service_fees')->name('hosting.service-fees');
        Route::post('hosting/service-fees', [ServiceFeeController::class, 'store'])
            ->middleware('permission:billing.service_fees')->name('hosting.service-fees.store');
        Route::put('hosting/service-fees/{config}', [ServiceFeeController::class, 'update'])
            ->middleware('permission:billing.service_fees')->name('hosting.service-fees.update');
        Route::delete('hosting/service-fees/{config}', [ServiceFeeController::class, 'destroy'])
            ->middleware('permission:billing.service_fees')->name('hosting.service-fees.destroy');

        // Everything the public forms produce. Without this the /contact and
        // /trip-support forms would be write-only.
        Route::get('inbox', [InboxController::class, 'index'])->middleware('permission:inbox.view')->name('inbox.index');
        Route::post('inbox/contact/{message}/handled', [InboxController::class, 'markHandled'])
            ->middleware('permission:inbox.manage')->name('inbox.handled');

        Route::get('contracts', [AdminContractController::class, 'index'])->middleware('permission:contracts.view')->name('contracts.index');
        Route::get('contracts/create', [AdminContractController::class, 'create'])->middleware('permission:contracts.send')->name('contracts.create');
        Route::post('contracts', [AdminContractController::class, 'store'])->middleware('permission:contracts.send')->name('contracts.store');
        Route::get('contracts/{contract}', [AdminContractController::class, 'show'])->middleware('permission:contracts.view')->name('contracts.show');
        Route::get('contracts/{contract}/signed.pdf', [AdminContractController::class, 'downloadSigned'])->middleware('permission:contracts.view')->name('contracts.download.signed');
        Route::get('contracts/{contract}/certificate.pdf', [AdminContractController::class, 'downloadCertificate'])->middleware('permission:contracts.view')->name('contracts.download.certificate');
        Route::post('contracts/{contract}/void', [AdminContractController::class, 'void'])->middleware('permission:contracts.void')->name('contracts.void');

        // FR-10.13: per-user login history + Service Usage Confirmation Certificate.
        // Used by admins responding to chargebacks.
        Route::get('users/{user}/login-history', [AdminUserCertificateController::class, 'loginHistory'])->middleware('permission:users.view')->name('users.login-history');
        Route::get('users/{user}/certificate.pdf', [AdminUserCertificateController::class, 'certificate'])->middleware('permission:users.view')->name('users.certificate');

        // -------------------------------------------------------------------
        // Settings & Configuration console. Panes render from SettingsSchema;
        // every write is validated against the allow-list, audited, and busts
        // the settings cache. Payment processors sit behind billing.processors
        // rather than settings.edit — gateway credentials are a sharper
        // capability than the rest of the console. Literal routes MUST precede
        // the settings/{group} wildcard.
        // -------------------------------------------------------------------
        Route::get('settings', [SettingsController::class, 'index'])->middleware('permission:settings.view')->name('settings.index');

        Route::get('settings/flags', [FeatureFlagController::class, 'index'])->middleware('permission:settings.view')->name('settings.flags');
        Route::put('settings/flags/{key}', [FeatureFlagController::class, 'update'])->middleware('permission:settings.edit')->name('settings.flags.update');

        Route::get('settings/processors', [PaymentProcessorController::class, 'index'])->middleware('permission:billing.processors')->name('settings.processors');
        Route::put('settings/processors/{code}', [PaymentProcessorController::class, 'update'])->middleware('permission:billing.processors')->name('settings.processors.update');
        Route::post('settings/processors/{code}/test', [PaymentProcessorController::class, 'test'])->middleware('permission:billing.processors')->name('settings.processors.test');

        Route::get('settings/templates', [EmailTemplateController::class, 'index'])->middleware('permission:settings.view')->name('settings.templates');
        Route::post('settings/templates', [EmailTemplateController::class, 'store'])->middleware('permission:settings.edit')->name('settings.templates.store');

        Route::get('settings/collections/{collection}', [ConfigCollectionController::class, 'index'])->middleware('permission:settings.view')->name('settings.collections.index');
        Route::post('settings/collections/{collection}', [ConfigCollectionController::class, 'store'])->middleware('permission:settings.edit')->name('settings.collections.store');
        Route::put('settings/collections/{collection}/{id}', [ConfigCollectionController::class, 'update'])->middleware('permission:settings.edit')->name('settings.collections.update');

        Route::get('settings/{group}', [SettingsController::class, 'showGroup'])->middleware('permission:settings.view')->name('settings.group');
        Route::put('settings/{group}', [SettingsController::class, 'updateGroup'])->middleware('permission:settings.edit')->name('settings.group.update');
    });

// Client-facing contract dashboard. Mounted at /account/contracts.
Route::middleware(['auth'])
    ->prefix('account')
    ->name('client.')
    ->group(function () {
        Route::get('contracts', [ClientContractController::class, 'index'])->name('contracts.index');
        Route::get('contracts/{contract}', [ClientContractController::class, 'show'])->name('contracts.show');
        Route::get('contracts/{contract}/sign', [ClientContractController::class, 'sign'])->name('contracts.sign');
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

// Inbound NMI webhooks. CSRF-excluded via bootstrap/app.php;
// authenticated via Webhook-Signature header (NmiWebhookSignatureVerifier).
Route::post('/webhooks/nmi', [NmiWebhookController::class, 'handle'])
    ->name('webhooks.nmi');

require __DIR__.'/auth.php';
