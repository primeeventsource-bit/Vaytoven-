<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\ConfigCollectionController;
use App\Http\Controllers\Admin\ContractController as AdminContractController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\FeatureFlagController;
use App\Http\Controllers\Admin\InboxController;
use App\Http\Controllers\Admin\MediaCollectionController;
use App\Http\Controllers\Admin\MediaLibraryController;
use App\Http\Controllers\Admin\MemberDocumentController;
use App\Http\Controllers\Admin\MemberProfileController;
use App\Http\Controllers\Admin\MemberServiceOrderController as AdminMemberServiceOrderController;
use App\Http\Controllers\Admin\OfferController as AdminOfferController;
use App\Http\Controllers\Admin\PaymentProcessorController;
use App\Http\Controllers\Admin\PropertyController as AdminPropertyController;
use App\Http\Controllers\Admin\PropertyAvailabilityController;
use App\Http\Controllers\Admin\PropertyDocumentController;
use App\Http\Controllers\Admin\PropertyPhotoController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\ServiceFeeController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffGuideController;
use App\Http\Controllers\Admin\UserCertificateController as AdminUserCertificateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CareersController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Client\ContractController as ClientContractController;
use App\Http\Controllers\CspReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventCenterController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\MemberPaymentController;
use App\Http\Controllers\MemberServicesController;
use App\Http\Controllers\HostingEnquiryController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\MemberEnquiryController;
use App\Http\Controllers\MemberOfferController;
use App\Http\Controllers\NewsletterSubscriptionController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\PressController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyBrowseController;
use App\Http\Controllers\PropertyPhotoStreamController;
use App\Http\Controllers\SavedPropertyController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SitePageController;
use App\Http\Controllers\TripSupportController;
use App\Http\Controllers\Webhooks\DocuSignWebhookController;
use App\Http\Controllers\Webhooks\NmiWebhookController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// Throttled like every other public form. Without it this writes a row
// per request from anonymous input, and it is the main lead form.
Route::post('/members/enquiry', [MemberEnquiryController::class, 'store'])
    ->middleware('throttle:10,1')->name('members.enquiry');

// ---------------------------------------------------------------------------
// Three-audience marketing pages — public, anonymous-friendly. Each surfaces
// a tab in the top nav (Stay → /properties, Become a Host → /become-a-host,
// Members → /members). Stay reuses the existing properties index.
// ---------------------------------------------------------------------------
Route::view('/become-a-host', 'hosts.show')->name('hosts.show');
Route::view('/members', 'members.show')->name('members.show');

// Event Centers — five major convention destinations, each linking to the
// venue's own calendar and to Vaytoven advertisements in that city. Public,
// because the visitor arriving from a convention has no account yet.
Route::get('/event-centers', [EventCenterController::class, 'index'])->name('event-centers.index');

// The staff training guide, generated from this environment on each download.
//
// Deliberately NOT named admin.* and not inside the admin group. Every route
// in that group must declare a permission: middleware - a structural guard
// stops an unguarded admin screen being added by accident - and this is a
// document rather than a screen. Picking a permission for it would also have
// locked out the narrowest role, which is exactly who needs to read it.
// Staff-only is enforced in the controller.
Route::get('/staff-guide.pdf', StaffGuideController::class)
    ->middleware('auth')->name('staff-guide');

// Member Services activation and payment.
//
// This is the one money flow Vaytoven operates, and it collects Vaytoven's own
// fee — advertising and listing services billed to the member. It is not a
// stay, and no money here belongs to anyone else.
//
// The member completes both steps themselves on their own device; staff may
// talk them through it by phone but never enter card details or submit on
// their behalf, so every sale is a customer-initiated e-commerce transaction.
Route::get('/member-services', [MemberServicesController::class, 'show'])->name('member-services.show');
Route::post('/member-services', [MemberServicesController::class, 'store'])
    ->middleware('throttle:10,1')->name('member-services.store');

// The reference is the credential here, so the page is rate limited: without
// it, the URL space could be walked to find live orders.
Route::get('/member-payment/{reference}', [MemberPaymentController::class, 'show'])
    ->middleware('throttle:60,1')->name('member-payment.show');
Route::post('/member-payment/{reference}', [MemberPaymentController::class, 'pay'])
    ->middleware('throttle:10,1')->name('member-payment.pay');

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
// "List your property or resort" — public, because an owner should not need an
// account to ask about being advertised.
//
// Served AT /host/onboarding because that URL is already published and linked;
// what changed is what lives there. It used to be payout enrollment, which
// described a model Vaytoven does not operate (guests paying Vaytoven, funds
// held, ACH payouts) and asked owners for bank details, government ID and tax
// forms. It is now a details-submission form that asks for none of that.
Route::get('/host/onboarding', [HostingEnquiryController::class, 'show'])->name('host.onboarding.index');
Route::post('/host/onboarding', [HostingEnquiryController::class, 'store'])
    ->middleware('throttle:10,1')->name('host.onboarding.store');

// Friendlier alias for the same form.
Route::get('/list-your-property', [HostingEnquiryController::class, 'show'])->name('host.list-your-property');
Route::post('/list-your-property', [HostingEnquiryController::class, 'store'])
    ->middleware('throttle:10,1')->name('host.list-your-property.store');

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
Route::post('/signup', [NewsletterSubscriptionController::class, 'store'])
    ->middleware('throttle:10,1')->name('signup.store');

// Property browse — public surface for visitors. Index supports ?q=, ?city=,
// ?destination= (alias for city), ?min_capacity=, ?max_price=.
Route::get('/properties', [PropertyBrowseController::class, 'index'])->name('properties.index');
Route::get('/properties/{property}', [PropertyBrowseController::class, 'show'])->name('properties.show');

// Uploaded listing images. The bucket is private, so they are streamed through
// the app; see PropertyPhotoStreamController for why not signed URLs.
// Declared BEFORE the {property} wildcard would otherwise swallow it.
Route::get('/property-photo/{photo}', PropertyPhotoStreamController::class)->name('properties.photo');

// There is deliberately no booking flow.
//
// Vaytoven advertises listings. It does not take reservations, hold dates,
// collect rental funds, or process payments between travelers and property
// owners. The checkout previously lived here behind a `stay.checkout` feature
// gate that defaulted to off — a gate is still a door, and it described a
// product this company does not sell. Travelers use Submit Offer instead
// (OfferController below); the host and guest settle everything directly once
// an offer is accepted and the dates are agreed.
//
// The bookings tables are retained as records of what happened before the
// model changed. Nothing on the site reads them.
Route::middleware(['auth', 'terms.current'])->group(function () {
    // Saving an advertisement to come back to.
    Route::get('/account/saved', [SavedPropertyController::class, 'index'])->name('saved.index');
    Route::post('/properties/{property}/save', [SavedPropertyController::class, 'toggle'])->name('saved.toggle');

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

    // /host/onboarding now serves the public "list your property or resort"
    // form — see the public route block above.
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

// Friendly alias for the nav and for anything a customer might type or be
// given verbally. A 301 rather than a second route rendering the same view:
// /legal/tos stays the single canonical URL, which matters because it is the
// address recorded on every terms_acceptance row.
Route::permanentRedirect('/terms', '/legal/tos')->name('terms');
Route::permanentRedirect('/terms-and-conditions', '/legal/tos');
Route::permanentRedirect('/privacy', '/legal/privacy');
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
        // Permanently remove an account. Distinct from deactivate, which is
        // reversible and keeps the record; refused when the account carries
        // payment, contract or acceptance records.
        Route::delete('users/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:users.delete')->name('users.destroy');
        Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->middleware('permission:users.deactivate')->name('users.deactivate');
        Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->middleware('permission:users.deactivate')->name('users.reactivate');

        // Issue a single-use temporary password. The permission already
        // existed in the catalog; nothing was wired to it until now.
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->middleware('permission:users.reset_password')->name('users.reset-password');

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
        Route::get('offers/{offer}', [AdminOfferController::class, 'show'])
            ->middleware('permission:offers.view')->name('offers.show');

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

        // Member 360 — one screen holding everything about one member.
        Route::get("members/{user}", [MemberProfileController::class, "show"])
            ->middleware("permission:members.view")->name("members.show");
        Route::post("members/{user}/notes", [MemberProfileController::class, "updateNotes"])
            ->middleware("permission:members.edit")->name("members.notes");

        // Member document store. Downloads are audited, so they go through the
        // controller rather than a public disk URL.
        Route::post('members/{user}/documents', [MemberDocumentController::class, 'store'])
            ->middleware('permission:members.edit')->name('members.documents.store');
        Route::get('members/{user}/documents/{document}', [MemberDocumentController::class, 'download'])
            ->middleware('permission:members.view')->name('members.documents.download');
        Route::delete('members/{user}/documents/{document}', [MemberDocumentController::class, 'destroy'])
            ->middleware('permission:members.edit')->name('members.documents.destroy');

        // One box over members, properties, offers and orders. Reads a name,
        // an email, a phone number in any format, an offer reference or an NMI
        // transaction id and works out which it was given.
        Route::get('search', [SearchController::class, 'index'])
            ->middleware('permission:users.view')->name('search');

        // Platform-wide listing activity: views, visitor map and the click
        // stream across every owner's advertisement.
        Route::get('activity', [ActivityController::class, 'index'])
            ->middleware('permission:reports.view')->name('activity.index');

        // The activity and IP audit log. Same permission as the analytics
        // screen: both are staff-only views of visitor behaviour.
        Route::get('activity/log', [ActivityController::class, 'log'])
            ->middleware('permission:audit.view')->name('activity.log');
        Route::get('activity/session/{session}', [ActivityController::class, 'session'])
            ->middleware('permission:audit.view')->name('activity.session');

        Route::get('activity/map', [ActivityController::class, 'map'])
            ->middleware('permission:audit.view')->name('activity.map');

        // Staff-created listings. An admin builds the listing for an existing
        // account or for an owner who has none yet; the owner is emailed
        // either way.
        Route::get('properties', [AdminPropertyController::class, 'index'])
            ->middleware('permission:properties.view')->name('properties.index');
        Route::get('properties/create', [AdminPropertyController::class, 'create'])
            ->middleware('permission:properties.create')->name('properties.create');
        Route::post('properties', [AdminPropertyController::class, 'store'])
            ->middleware('permission:properties.create')->name('properties.store');

        // ── Shared photo library ──────────────────────────────────────────
        //
        // Stock photography kept once and reused, rather than re-uploaded for
        // every new member. The media.* permissions have existed in the RBAC
        // seeder since it was written; this is what finally uses them.
        //
        // The folder routes are declared BEFORE the {asset} routes on purpose:
        // "folders" would otherwise be matched as an asset id and 404.
        Route::get('media', [MediaLibraryController::class, 'index'])
            ->middleware('permission:media.view')->name('media.index');
        Route::post('media', [MediaLibraryController::class, 'store'])
            ->middleware('permission:media.upload')->name('media.store');

        Route::post('media/folders', [MediaCollectionController::class, 'store'])
            ->middleware('permission:media.upload')->name('media.folders.store');
        Route::patch('media/folders/{collection}', [MediaCollectionController::class, 'update'])
            ->middleware('permission:media.edit')->name('media.folders.update');
        Route::delete('media/folders/{collection}', [MediaCollectionController::class, 'destroy'])
            ->middleware('permission:media.delete')->name('media.folders.destroy');

        // Streamed through the app because the bucket is private, and gated on
        // media.view because stock photography is internal until it is used.
        Route::get('media/{asset}/file', [MediaLibraryController::class, 'show'])
            ->middleware('permission:media.view')->name('media.show');
        Route::patch('media/{asset}', [MediaLibraryController::class, 'update'])
            ->middleware('permission:media.edit')->name('media.update');
        Route::delete('media/{asset}', [MediaLibraryController::class, 'destroy'])
            ->middleware('permission:media.delete')->name('media.destroy');

        // The listing builder. Editing is a separate permission from creating:
        // a specialist may maintain listings without being able to add new ones
        // against a package allowance.
        Route::get('properties/{property}', [AdminPropertyController::class, 'show'])
            ->middleware('permission:properties.view')->name('properties.show');
        Route::get('properties/{property}/edit', [AdminPropertyController::class, 'edit'])
            ->middleware('permission:properties.edit')->name('properties.edit');
        Route::patch('properties/{property}', [AdminPropertyController::class, 'update'])
            ->middleware('permission:properties.edit')->name('properties.update');
        // Publishing is a separate permission from editing: a specialist may
        // maintain a listing without deciding when it goes live.
        // Deleting a listing is separate from publishing it. Refused once the
        // listing has been advertised under a paid order - see the controller.
        Route::delete('properties/{property}', [AdminPropertyController::class, 'destroy'])
            ->middleware('permission:properties.delete')->name('properties.destroy');
        Route::post('properties/{property}/status', [AdminPropertyController::class, 'transition'])
            ->middleware('permission:properties.publish')->name('properties.transition');

        // Photos. Same permission as the builder; ownership is checked in the
        // controller because properties.edit is also granted to hosts.
        Route::post('properties/{property}/photos', [PropertyPhotoController::class, 'store'])
            ->middleware('permission:properties.edit')->name('properties.photos.store');
        Route::patch('properties/{property}/photos/{photo}', [PropertyPhotoController::class, 'update'])
            ->middleware('permission:properties.edit')->name('properties.photos.update');
        Route::post('properties/{property}/photos/{photo}/cover', [PropertyPhotoController::class, 'cover'])
            ->middleware('permission:properties.edit')->name('properties.photos.cover');
        // Pull photos in from the shared library instead of uploading.
        Route::post('properties/{property}/photos/from-library', [PropertyPhotoController::class, 'fromLibrary'])
            ->middleware('permission:properties.edit')->name('properties.photos.from-library');
        Route::post('properties/{property}/photos/{photo}/transform', [PropertyPhotoController::class, 'transform'])
            ->middleware('permission:properties.edit')->name('properties.photos.transform');
        Route::post('properties/{property}/photos/reorder', [PropertyPhotoController::class, 'reorder'])
            ->middleware('permission:properties.edit')->name('properties.photos.reorder');
        Route::delete('properties/{property}/photos/{photo}', [PropertyPhotoController::class, 'destroy'])
            ->middleware('permission:properties.edit')->name('properties.photos.destroy');

        // Availability. Members may manage their own dates; the ownership
        // check lives in the controller because properties.edit is granted
        // to the RBAC host role.
        Route::post('properties/{property}/availability', [PropertyAvailabilityController::class, 'store'])
            ->middleware('permission:properties.availability')->name('properties.availability.store');
        Route::patch('properties/{property}/availability/{week}', [PropertyAvailabilityController::class, 'update'])
            ->middleware('permission:properties.availability')->name('properties.availability.update');
        Route::delete('properties/{property}/availability/{week}', [PropertyAvailabilityController::class, 'destroy'])
            ->middleware('permission:properties.availability')->name('properties.availability.destroy');

        // Documents filed against one property. Same store as member
        // documents, with a property attached.
        Route::post('properties/{property}/documents', [PropertyDocumentController::class, 'store'])
            ->middleware('permission:properties.edit')->name('properties.documents.store');
        Route::get('properties/{property}/documents/{document}', [PropertyDocumentController::class, 'download'])
            ->middleware('permission:properties.view')->name('properties.documents.download');
        Route::delete('properties/{property}/documents/{document}', [PropertyDocumentController::class, 'destroy'])
            ->middleware('permission:properties.edit')->name('properties.documents.destroy');

        // Member Services activations — what was ordered and whether it paid.
        Route::get('member-services', [AdminMemberServiceOrderController::class, 'index'])
            ->middleware('permission:billing.view')->name('member-services.index');
        Route::post('member-services/{order}/cancel', [AdminMemberServiceOrderController::class, 'cancel'])
            ->middleware('permission:billing.manage')->name('member-services.cancel');
        Route::post('member-services/{order}/activate', [AdminMemberServiceOrderController::class, 'activate'])
            ->middleware('permission:properties.publish')->name('member-services.activate');

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
        // Recovery for contracts whose PDFs were written to a disk that did not
        // survive a deploy. DocuSign keeps the authoritative copy, so the file
        // is re-pullable — the local one was only ever a convenience copy.
        Route::post('contracts/{contract}/refetch', [AdminContractController::class, 'refetchDocuments'])->middleware('permission:contracts.send')->name('contracts.refetch');
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

// Public sitemap. The URL list is an allow-list inside the controller, not a
// walk of the router — see SitemapController.
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Where browsers post CSP violations. Posted by the browser, so it cannot
// carry a CSRF token and cannot be authenticated; rate limited instead, and
// the payload is treated as hostile input by the controller.
Route::post('/security/csp-report', CspReportController::class)
    ->middleware('throttle:60,1')
    ->name('security.csp-report');

// Inbound DocuSign Connect webhook. CSRF-excluded via bootstrap/app.php;
// authenticated via HMAC signature (WebhookVerifier).
Route::post('/webhooks/docusign', DocuSignWebhookController::class)
    ->name('webhooks.docusign');

// Inbound NMI webhooks. CSRF-excluded via bootstrap/app.php;
// authenticated via Webhook-Signature header (NmiWebhookSignatureVerifier).
Route::post('/webhooks/nmi', [NmiWebhookController::class, 'handle'])
    ->name('webhooks.nmi');

require __DIR__.'/auth.php';
