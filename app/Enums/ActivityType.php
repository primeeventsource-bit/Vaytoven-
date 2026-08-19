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
            'members' => [
                self::PackageSelected, self::PropertySubmitted, self::PropertyEdited,
                self::ImagesUploaded, self::AvailabilityChanged, self::DashboardAccessed,
                self::ProfileUpdated,
            ],
            'ads' => [
                self::AdvertisementClicked, self::AdvertisementPreviewed,
                self::AdvertisementActivated, self::AdvertisementPaused,
            ],
            'offers' => [
                self::InquiryStarted, self::OfferStarted, self::OfferSubmitted,
            ],
            'contracts' => [
                self::ContractOpened, self::ContractSigned,
            ],
            'payments' => [
                self::CheckoutOpened, self::PaymentFormLoaded, self::PaymentSubmitted,
                self::PaymentApproved, self::PaymentDeclined, self::ReceiptViewed,
            ],
            'logins' => [
                self::AccountCreated, self::EmailVerified, self::LoginSucceeded,
                self::LoginFailed, self::LoggedOut, self::PasswordReset,
            ],
            'admin' => [self::AdminAction],
        ];

        return array_map(fn (self $c) => $c->value, $map[$group] ?? []);
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
