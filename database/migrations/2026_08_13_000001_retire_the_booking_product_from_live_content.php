<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the booking product from content that already exists in the database.
 *
 * Deleting code is not enough here. Two subsystems keep their content in the
 * database and are populated by seeders, and the Laravel Cloud deploy command
 * is `php artisan migrate --force` — seeders do not run. Editing
 * HelpArticleSeeder and SettingsSchema therefore changes nothing on a live
 * environment: the rows are already there.
 *
 * That gap has bitten this project before. New legal text was published while
 * terms_versions still pointed at the superseded document, because the seeder
 * that materialises it never ran on deploy. A migration is the only thing here
 * that is guaranteed to execute.
 *
 * Removed:
 *   - Nine help articles documenting cancellation policies, a checkout service
 *     fee, card refund timings, how to book, how to modify a booking, what's
 *     included in a stay, and $250,000 of damage cover.
 *   - The whole booking.* settings group, including the stay_checkout_enabled
 *     switch that could have turned the checkout back on.
 */
return new class extends Migration
{
    private const RETIRED_HELP_SLUGS = [
        'cancellation-flexible',
        'cancellation-moderate',
        'cancellation-strict',
        'service-fee',
        'refund-timing',
        'how-to-book',
        'modify-booking',
        'whats-included',
        'damage-cover',
    ];

    private const RETIRED_SETTING_KEYS = [
        'booking.stay_checkout_enabled',
        'booking.min_nights',
        'booking.max_nights',
        'booking.advance_window_days',
        'booking.checkin_time',
        'booking.checkout_time',
        'booking.instant_book_default',
        'booking.default_cancellation_policy',
        'booking.allow_same_day',
        'booking.hold_minutes',
    ];

    public function up(): void
    {
        if (Schema::hasTable('help_articles')) {
            DB::table('help_articles')
                ->whereIn('slug', self::RETIRED_HELP_SLUGS)
                ->delete();
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->whereIn('key', self::RETIRED_SETTING_KEYS)
                ->delete();

            // SettingsRepository caches per key for its TTL, so a deleted row
            // would keep answering from Redis until it aged out.
            foreach (self::RETIRED_SETTING_KEYS as $key) {
                Cache::forget("settings:{$key}");
            }
        }

        // SupportTicket casts `category` to the enum, so a row holding a
        // retired value throws on hydration and takes the admin inbox down
        // with it. Both live environments read zero support_tickets when this
        // was written, but "currently empty" is not a guarantee and the cost
        // of being wrong is an exception in a queue nobody can then open.
        if (Schema::hasTable('support_tickets')) {
            $remap = [
                'booking'      => 'reaching_an_owner',
                'cancellation' => 'reaching_an_owner',
                'payment'      => 'billing',
            ];

            foreach ($remap as $old => $new) {
                DB::table('support_tickets')
                    ->where('category', $old)
                    ->update(['category' => $new]);
            }
        }

        // Deliberately NOT dropped: bookings, booking_state_transitions,
        // charges, refunds, payment_intents. Those hold records of what
        // actually happened before the model changed, and chargeback evidence
        // is built from them. Nothing on the site reads them any more; they
        // are retained as history, not as a product.
    }

    public function down(): void
    {
        // Irreversible by design. Re-inserting help articles that describe a
        // booking product, or a setting that re-enables a checkout, is not
        // something a rollback should quietly do. Restore from a backup if
        // this genuinely needs undoing.
    }
};
