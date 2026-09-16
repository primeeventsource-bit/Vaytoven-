<?php

namespace App\Enums;

/**
 * The vocabulary of the activity log.
 *
 * Meaningful application events only. No mouse movement, no keystrokes, no
 * scroll depth: those make the trail unreadable, the storage unmanageable, and
 * collect far more about a person than answering "what happened" requires. A
 * row here should be something a human would say out loud — "they opened the
 * gallery", "they submitted an offer".
 *
 * An enum rather than free strings because the filters, the evidence bundle
 * and the map all group by these. A typo in a string literal produces a
 * category that silently contains one event and a filter that silently misses
 * it.
 */
enum ActivityType: string
{
    // --- website -----------------------------------------------------------
    case WebsiteVisited      = 'website.visited';
    case PageViewed          = 'page.viewed';
    case PropertyViewed      = 'property.viewed';
    case SearchPerformed     = 'search.performed';
    case MapOpened           = 'map.opened';
    case AmenityViewed       = 'amenity.viewed';
    case GalleryOpened       = 'gallery.opened';
    case AdvertisementClicked = 'advertisement.clicked';
    case FavoriteSaved       = 'favorite.saved';
    case FavoriteRemoved     = 'favorite.removed';
    case InquiryStarted      = 'inquiry.started';
    case OfferStarted        = 'offer.started';
    case OfferSubmitted      = 'offer.submitted';

    // --- account -----------------------------------------------------------
    case AccountCreated      = 'account.created';
    case EmailVerified       = 'account.email_verified';
    case LoginSucceeded      = 'account.login_succeeded';
    case LoginFailed         = 'account.login_failed';
    case LoggedOut           = 'account.logged_out';
    case PasswordReset       = 'account.password_reset';
    case ProfileUpdated      = 'account.profile_updated';
    case TermsAccepted       = 'account.terms_accepted';

    // --- member ------------------------------------------------------------
    case PackageSelected      = 'member.package_selected';
    case PropertySubmitted    = 'member.property_submitted';
    case PropertyEdited       = 'member.property_edited';
    case ImagesUploaded       = 'member.images_uploaded';
    case AvailabilityChanged  = 'member.availability_changed';
    case AdvertisementPreviewed = 'member.advertisement_previewed';
    case AdvertisementActivated = 'member.advertisement_activated';
    case AdvertisementPaused    = 'member.advertisement_paused';
    case ContractOpened       = 'member.contract_opened';
    case ContractSigned       = 'member.contract_signed';
    case DashboardAccessed    = 'member.dashboard_accessed';

    // Fulfillment evidence. Written ONLY by the server, ONLY for the
    // authenticated non-staff owner of the listing — never for staff viewing
    // or activating it. See AdvertisementFulfillment.
    case MemberAdvertisementFirstAccessed = 'member.advertisement_first_accessed';
    case MemberAdvertisementAccessed      = 'member.advertisement_accessed';
    case MemberAdvertisementReviewed      = 'member.advertisement_reviewed';

    // First sign-in and the enrollment incentive. Each is its own event:
    // signing in is not seeing the incentive, seeing it is not acknowledging
    // it, acknowledging it is not receiving it, and none of them is accepting
    // an advertisement.
    case MemberFirstLogin                 = 'member.first_login';
    case MemberIncentivePresented         = 'member.incentive_presented';
    case MemberIncentiveAcknowledged      = 'member.incentive_acknowledged';
    case MemberIncentiveDelivered         = 'admin.incentive_delivered';
    case MemberAdvertisementAccepted      = 'member.advertisement_accepted';

    // --- payment -----------------------------------------------------------
    // Nothing in this group ever carries a card number or a CVV. See
    // TrackingService::filterMetadata().
    case CheckoutOpened      = 'payment.checkout_opened';
    case PaymentFormLoaded   = 'payment.form_loaded';
    case PaymentSubmitted    = 'payment.submitted';
    case PaymentApproved     = 'payment.approved';
    case PaymentDeclined     = 'payment.declined';
    case ReceiptViewed       = 'payment.receipt_viewed';

    // --- staff -------------------------------------------------------------
    case AdminAction         = 'admin.action';
    case AdvertisementCreated = 'admin.advertisement_created';
    case FulfillmentCorrected = 'admin.fulfillment_corrected';

    /**
     * Filter tabs in the Activity Center, in display order.
     *
     * @return array<string, string>
     */
    public static function groups(): array
    {
        return [
            'all'       => 'All activity',
            'visitors'  => 'Visitors',
            'members'   => 'Members',
            'ads'       => 'Ads',
            'offers'    => 'Offers',
            'contracts' => 'Contracts',
            'payments'  => 'Payments',
            'logins'    => 'Logins',
            'admin'     => 'Admin activity',
        ];
    }

    /** @return array<int, string> the event values a filter tab covers */
    public static function valuesForGroup(string $group): array
    {
        $map = [
            'visitors' => [
                self::WebsiteVisited, self::PageViewed, self::PropertyViewed,
                self::SearchPerformed, self::MapOpened, self::AmenityViewed,
                self::GalleryOpened, self::FavoriteSaved, self::FavoriteRemoved,
            ],
            // Member-generated only. ActivityLogQuery also excludes any row
            // whose actor was staff: the listing tools write these same types
            // when an admin edits a member's listing.
            'members' => [
                self::PackageSelected, self::PropertySubmitted, self::PropertyEdited,
                self::ImagesUploaded, self::AvailabilityChanged, self::DashboardAccessed,
                self::ProfileUpdated,
                self::MemberAdvertisementFirstAccessed, self::MemberAdvertisementAccessed,
                self::MemberAdvertisementReviewed, self::MemberAdvertisementAccepted,
                self::MemberFirstLogin, self::MemberIncentivePresented, self::MemberIncentiveAcknowledged,
            ],
            // Both sides of an advertisement's life, whoever the actor.
            'ads' => [
                self::AdvertisementCreated,
                self::AdvertisementClicked, self::AdvertisementPreviewed,
                self::AdvertisementActivated, self::AdvertisementPaused,
                self::MemberAdvertisementFirstAccessed, self::MemberAdvertisementAccepted,
            ],
            'offers' => [
                self::InquiryStarted, self::OfferStarted, self::OfferSubmitted,
            ],
            'contracts' => [
                self::ContractOpened, self::ContractSigned, self::TermsAccepted,
            ],
            'payments' => [
                self::CheckoutOpened, self::PaymentFormLoaded, self::PaymentSubmitted,
                self::PaymentApproved, self::PaymentDeclined, self::ReceiptViewed,
            ],
            'logins' => [
                self::AccountCreated, self::EmailVerified, self::LoginSucceeded,
                self::LoginFailed, self::LoggedOut, self::PasswordReset, self::MemberFirstLogin,
            ],
            // Explicitly-staff types. ActivityLogQuery adds every
            // staffActionable() row whose actor was staff.
            'admin' => [self::AdminAction, self::AdvertisementCreated, self::FulfillmentCorrected, self::MemberIncentiveDelivered],
        ];

        return array_map(fn (self $c) => $c->value, $map[$group] ?? []);
    }

    /**
     * Types that are member activity when a member does them and admin
     * activity when staff do them — the listing tools share one vocabulary.
     *
     * @return array<int, string>
     */
    public static function staffActionable(): array
    {
        return array_map(fn (self $c) => $c->value, [
            self::PropertySubmitted, self::PropertyEdited, self::ImagesUploaded,
            self::AvailabilityChanged, self::PackageSelected, self::ProfileUpdated,
            self::DashboardAccessed, self::LoginSucceeded, self::LoggedOut, self::AccountCreated,
            self::AdvertisementPreviewed, self::AdvertisementActivated, self::AdvertisementPaused,
            self::ContractOpened, self::ContractSigned,
        ]);
    }

    /**
     * Types that may only ever be written for a non-staff account.
     *
     * @return array<int, string>
     */
    public static function memberOnly(): array
    {
        return array_map(fn (self $c) => $c->value, [
            self::MemberAdvertisementFirstAccessed,
            self::MemberAdvertisementAccessed,
            self::MemberAdvertisementReviewed,
            self::MemberAdvertisementAccepted,
            self::MemberFirstLogin,
            self::MemberIncentivePresented,
            self::MemberIncentiveAcknowledged,
        ]);
    }

    /**
     * Events that carry full device + location evidence when a member does
     * them: the member's identity and address on file, and a comparison of
     * that address with the event's OWN approximate IP location.
     *
     * @return array<int, string>
     */
    public static function deviceEvidence(): array
    {
        return array_map(fn (self $c) => $c->value, [
            self::LoginSucceeded, self::AccountCreated, self::ProfileUpdated, self::PasswordReset,
            self::TermsAccepted, self::ContractOpened, self::ContractSigned,
            self::MemberAdvertisementFirstAccessed, self::MemberAdvertisementAccessed,
            self::MemberAdvertisementReviewed, self::MemberAdvertisementAccepted,
            self::MemberFirstLogin, self::MemberIncentivePresented, self::MemberIncentiveAcknowledged,
        ]);
    }

    public function label(): string
    {
        return match ($this) {
            self::WebsiteVisited        => 'Website visited',
            self::PageViewed            => 'Page viewed',
            self::PropertyViewed        => 'Property advertisement viewed',
            self::SearchPerformed       => 'Search performed',
            self::MapOpened             => 'Map opened',
            self::AmenityViewed         => 'Amenities viewed',
            self::GalleryOpened         => 'Photo gallery opened',
            self::AdvertisementClicked  => 'Advertisement clicked',
            self::FavoriteSaved         => 'Advertisement saved',
            self::FavoriteRemoved       => 'Advertisement unsaved',
            self::InquiryStarted        => 'Inquiry started',
            self::OfferStarted          => 'Offer form opened',
            self::OfferSubmitted        => 'Offer submitted',
            self::AccountCreated        => 'Account created',
            self::EmailVerified         => 'Email verified',
            self::LoginSucceeded        => 'Login successful',
            self::LoginFailed           => 'Login failed',
            self::LoggedOut             => 'Logged out',
            self::PasswordReset         => 'Password reset',
            self::ProfileUpdated        => 'Profile updated',
            self::PackageSelected       => 'Package selected',
            self::PropertySubmitted     => 'Property submitted',
            self::PropertyEdited        => 'Property edited',
            self::ImagesUploaded        => 'Images uploaded',
            self::AvailabilityChanged   => 'Availability changed',
            self::AdvertisementPreviewed => 'Advertisement previewed',
            self::AdvertisementActivated => 'Advertisement activated',
            self::AdvertisementPaused    => 'Advertisement paused',
            self::ContractOpened        => 'Contract opened',
            self::ContractSigned        => 'Contract signed',
            self::DashboardAccessed     => 'Member dashboard accessed',
            self::CheckoutOpened        => 'Checkout opened',
            self::PaymentFormLoaded     => 'Payment form loaded',
            self::PaymentSubmitted      => 'Payment submitted',
            self::PaymentApproved       => 'Payment approved',
            self::PaymentDeclined       => 'Payment declined',
            self::ReceiptViewed         => 'Receipt viewed',
            self::AdminAction           => 'Admin action',
            self::AdvertisementCreated  => 'Advertisement created',
            self::FulfillmentCorrected  => 'Fulfillment record corrected',
            self::MemberAdvertisementFirstAccessed => 'Member Advertisement Accessed',
            self::MemberAdvertisementAccessed      => 'Member advertisement accessed again',
            self::MemberAdvertisementReviewed      => 'Member advertisement reviewed',
            self::TermsAccepted                    => 'Terms accepted',
            self::MemberFirstLogin                 => 'Member First Login',
            self::MemberIncentivePresented         => 'Member Incentive Presented',
            self::MemberIncentiveAcknowledged      => 'Member Incentive Acknowledged',
            self::MemberIncentiveDelivered         => 'Member incentive delivery recorded',
            self::MemberAdvertisementAccepted      => 'Member Advertisement Accepted',
        };
    }

    /**
     * Events that belong in a dispute evidence bundle.
     *
     * The narrow set that answers "did this person agree to this and pay for
     * it": account creation, acceptance, payment, and the advertising the
     * money bought. Browsing history is deliberately excluded — it pads the
     * file with unrelated visitor records and proves nothing about consent.
     *
     * @return array<int, string>
     */
    public static function evidenceTrail(): array
    {
        return array_map(fn (self $c) => $c->value, [
            self::AccountCreated,
            self::EmailVerified,
            self::ContractOpened,
            self::ContractSigned,
            self::CheckoutOpened,
            self::PaymentSubmitted,
            self::PaymentApproved,
            self::PaymentDeclined,
            self::LoginSucceeded,
            self::PropertySubmitted,
            self::AdvertisementActivated,
            self::MemberAdvertisementFirstAccessed,
            self::MemberAdvertisementAccepted,
            self::TermsAccepted,
            self::MemberFirstLogin,
            self::MemberIncentivePresented,
            self::MemberIncentiveAcknowledged,
            self::MemberIncentiveDelivered,
        ]);
    }

    /**
     * Events a browser is allowed to report about itself.
     *
     * The ingest endpoint is public and unauthenticated, because a marketing
     * page has to be able to post a page view before anyone signs in. That
     * makes anything it accepts forgeable: a request posted by hand is
     * indistinguishable from one posted by the site's own script.
     *
     * So it accepts only this list — interactions that exist purely in the
     * browser and that nothing depends on. Opening a gallery is colour on a
     * session journey. Signing a contract, paying, logging in, uploading a
     * document and activating an advertisement are all facts the SERVER
     * observed while performing them, and every one of them is written by the
     * code that did the work.
     *
     * Without this, a visitor could post account.login_succeeded or
     * payment.approved into an append-only log and it would sit there next to
     * the genuine rows looking exactly like them. Append-only guarantees no row
     * is altered afterwards; it says nothing about whether the row was true
     * when it arrived. Notice that every value in evidenceTrail() is absent
     * here, and that is the property worth keeping.
     *
     * @return array<int, string>
     */
    public static function clientReportable(): array
    {
        return array_map(fn (self $c) => $c->value, [
            self::WebsiteVisited,
            self::PageViewed,
            self::PropertyViewed,
            self::SearchPerformed,
            self::MapOpened,
            self::AmenityViewed,
            self::GalleryOpened,
            self::AdvertisementClicked,
            self::InquiryStarted,
            self::OfferStarted,
        ]);
    }

    /**
     * Free-form types the tracking script has always sent.
     *
     * page_view predates this enum and is what every historical row is called.
     * Renaming it to page.viewed now would split one fact across two spellings
     * and quietly break every filter and count that reads the old name, so it
     * stays permitted exactly as it is.
     *
     * @return array<int, string>
     */
    public static function legacyClientTypes(): array
    {
        return [
            'page_view',         // every page, from vyt-track.js
            'cta_click',         // data-track-cta links across the marketing pages
            'enquiry_submitted', // landing conversion; the enquiry itself is stored server-side
            'search_performed',  // predates search.performed
        ];
    }
}
