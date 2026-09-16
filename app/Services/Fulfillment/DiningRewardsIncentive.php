<?php

namespace App\Services\Fulfillment;

/**
 * The enrollment thank-you: $300 in Dining Rewards.
 *
 * What the member is shown is versioned and hashed like the advertisement
 * acknowledgement, so the record says exactly which offer, at what value,
 * from which provider was put in front of them. Change the offer → change
 * the version.
 */
final class DiningRewardsIncentive
{
    public const KEY      = 'dining-rewards-300';
    public const VERSION  = '2026-09-16.v1';
    public const NAME     = '$300 Dining Rewards';
    public const VALUE    = '$300';
    public const PROVIDER = 'Creative Marketing Incentives';

    public const VIEW_BUTTON     = 'VIEW MY DINING REWARD';
    public const CONTINUE_BUTTON = 'CONTINUE & CLAIM MY REWARD';

    /** @var list<string> */
    public const INCLUDED = [
        'Redeemable at thousands of participating restaurants nationwide',
        'Certificates issued in $25 denominations · use one per visit',
        "Independent verification via Restaurant.com's participating merchant network",
        'Redemption valid nationwide — no travel required to use',
    ];

    public const FINE_PRINT = 'Certificate fulfilled by Creative Marketing Incentives, an independent third-party provider. '
        .'Recipient responsible for all applicable taxes, redemption fees, and any usage restrictions per certificate terms. '
        .'Certificate provided as a thank-you upon enrollment in the Vaytoven Managed Listing Program; '
        .'full program terms available at vaytoven.com/legal.';

    public static function presentationHash(): string
    {
        return hash('sha256', implode("\n", [
            self::KEY, self::VERSION, self::NAME, self::VALUE, self::PROVIDER,
            ...self::INCLUDED, self::FINE_PRINT,
        ]));
    }

    /** @return array<string, string> the incentive columns of an audit record */
    public static function columns(): array
    {
        return [
            'incentive_key'               => self::KEY,
            'incentive_name'              => self::NAME,
            'incentive_value'             => self::VALUE,
            'incentive_provider'          => self::PROVIDER,
            'incentive_version'           => self::VERSION,
            'incentive_presentation_hash' => self::presentationHash(),
        ];
    }
}
