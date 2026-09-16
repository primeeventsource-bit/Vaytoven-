<?php

namespace App\Services\Fulfillment;

use App\Models\User;
use Throwable;

/**
 * The enrollment thank-you offers staff can send to Managed Listing Program
 * clients, and which one a given client receives.
 *
 * A client gets the offer assigned on their profile, or failing that the
 * default chosen on Marketing → Incentive offers, or failing that Dining
 * Rewards — the offer every client before this catalog was set up to receive.
 */
final class IncentiveCatalog
{
    public const DEFAULT_KEY = 'dining-rewards-300';

    public const SETTING_DEFAULT = 'users.default_incentive';

    private const FINE_PRINT = 'Certificate fulfilled by Creative Marketing Incentives, an independent third-party provider. '
        .'Recipient responsible for all applicable taxes, redemption fees, and any usage restrictions per certificate terms. '
        .'Certificate provided as a thank-you upon enrollment in the Vaytoven Managed Listing Program; '
        .'full program terms available at vaytoven.com/legal.';

    /** @return array<string, Incentive> keyed by incentive key, in display order */
    public static function all(): array
    {
        $offers = [
            new Incentive(
                key: 'dining-rewards-300', version: '2026-09-16.v1',
                name: '$300 Dining Rewards', value: '$300', provider: 'Creative Marketing Incentives',
                eyebrow: 'Our thank-you when you enroll', headline: '$300', subheadline: 'in Dining Rewards',
                tagline: 'Breakfast · Lunch · Dinner', theme: 'pink', icon: 'dining', viewButton: 'VIEW MY DINING REWARD',
                included: [
                    'Redeemable at thousands of participating restaurants nationwide',
                    'Certificates issued in $25 denominations · use one per visit',
                    "Independent verification via Restaurant.com's participating merchant network",
                    'Redemption valid nationwide — no travel required to use',
                ],
                finePrint: self::FINE_PRINT,
            ),
            new Incentive(
                key: 'airfare-hotel-2x2', version: '2026-09-16.v1',
                name: '2 Airfares + 2 Nights Hotel', value: '2 airfares + 2 hotel nights', provider: 'Creative Marketing Incentives',
                eyebrow: 'Our premier thank-you when you enroll', headline: '2 Airfares', subheadline: '+ 2 Nights Hotel',
                tagline: 'Choose from 23 U.S. destinations', theme: 'pink-purple', icon: 'travel', viewButton: 'VIEW MY TRAVEL REWARD',
                included: [
                    'Two roundtrip airfares · two hotel nights included',
                    'Los Angeles · New York · Miami · Las Vegas · Chicago · New Orleans · plus 17 more',
                    // The artwork's line named a sales format the brand never
                    // names; the promise is the same.
                    'No sales presentations. No tours. Confirmed in writing.',
                    '60-day advance booking required · departure fees apply',
                ],
                finePrint: self::FINE_PRINT,
            ),
            new Incentive(
                key: 'hotel-savings-400', version: '2026-09-16.v1',
                name: '$400 Hotel Savings', value: '$400', provider: 'Creative Marketing Incentives',
                eyebrow: 'Our thank-you when you enroll', headline: '$400', subheadline: 'in Hotel Savings',
                tagline: 'at 800,000+ properties worldwide', theme: 'purple', icon: 'hotel', viewButton: 'VIEW MY HOTEL REWARD',
                included: self::hotelIncluded(),
                finePrint: self::FINE_PRINT,
            ),
            new Incentive(
                key: 'hotel-savings-500', version: '2026-09-16.v1',
                name: '$500 Hotel Savings', value: '$500', provider: 'Creative Marketing Incentives',
                eyebrow: 'Our thank-you when you enroll', headline: '$500', subheadline: 'in Hotel Savings',
                tagline: 'at 800,000+ properties worldwide', theme: 'purple-deep', icon: 'hotel', viewButton: 'VIEW MY HOTEL REWARD',
                included: self::hotelIncluded(),
                finePrint: self::FINE_PRINT,
            ),
        ];

        $keyed = [];
        foreach ($offers as $offer) {
            $keyed[$offer->key] = $offer;
        }

        return $keyed;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function find(?string $key): ?Incentive
    {
        return $key ? (self::all()[$key] ?? null) : null;
    }

    public static function defaultKey(): string
    {
        try {
            $key = (string) setting(self::SETTING_DEFAULT, self::DEFAULT_KEY);
        } catch (Throwable) {
            $key = self::DEFAULT_KEY;
        }

        return self::find($key) ? $key : self::DEFAULT_KEY;
    }

    public static function default(): Incentive
    {
        return self::all()[self::defaultKey()];
    }

    /** The offer this client receives: their assignment, else the default. */
    public static function forMember(User $member): Incentive
    {
        return self::find($member->incentive_key) ?? self::default();
    }

    /** @return list<string> */
    private static function hotelIncluded(): array
    {
        return [
            'Redeemable at over 800,000 hotels worldwide',
            'Savings applied against nightly rates at booking',
            'No blackout dates on most destinations',
            'Valid for 18 months from certificate issue date',
        ];
    }
}
