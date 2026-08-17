<?php

namespace App\Enums;

/**
 * The three Member Services packages.
 *
 * Prices live in settings (member_services.*_price_per_week_cents) so they can
 * be changed without a deploy, but an ORDER never reads them again after it is
 * created: the price per week is snapshotted onto the row. Raising Gold from
 * $449 to $499 must not reprice a payment link a member is already holding.
 *
 * Everything a package IS — its badge, tagline, property allowance, feature
 * levels and bonus benefit — is defined here and nowhere else. The homepage
 * cards, the activation page and the comparison table all render from these
 * methods, so the tiers cannot say one thing in one place and something else
 * in another.
 */
enum MemberServicePackage: string
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    // ---------------------------------------------------------- presentation

    public function emoji(): string
    {
        return match ($this) {
            self::Bronze => '🥉',
            self::Silver => '🥈',
            self::Gold   => '👑',
        };
    }

    /** Where the package sits in the ladder. */
    public function position(): string
    {
        return match ($this) {
            self::Bronze => 'ESSENTIAL',
            self::Silver => 'MOST POPULAR',
            self::Gold   => 'PREMIER',
        };
    }

    /** Badge shown on the card, or null where the tier carries none. */
    public function badge(): ?string
    {
        return match ($this) {
            self::Bronze => null,
            self::Silver => '⭐ MOST POPULAR',
            self::Gold   => '👑 PREMIER PACKAGE',
        };
    }

    public function headline(): string
    {
        return match ($this) {
            self::Bronze => 'ESSENTIAL VISIBILITY',
            self::Silver => 'ENHANCED EXPOSURE',
            self::Gold   => 'PREMIER VISIBILITY',
        };
    }

    public function tagline(): string
    {
        return match ($this) {
            self::Bronze => 'Your First Step to Greater Visibility.',
            self::Silver => 'Double the Opportunity. Elevate Your Exposure.',
            self::Gold   => 'More Properties. More Features. Maximum Visibility.',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Bronze => 'Showcase one property through the Vaytoven platform with professional presentation, member inquiry tools, availability display, direct communication features, and standard platform visibility.',
            self::Silver => 'Showcase up to two properties while unlocking enhanced visibility, featured listing capabilities, priority placement, multi-property management, enhanced promotional exposure, and priority inquiry notifications.',
            self::Gold   => "Showcase up to three properties with Vaytoven's highest level of platform exposure. Gold unlocks premier placement, premium property presentation, featured-area eligibility, advanced multi-property management, priority support, and Vaytoven's strongest promotional feature set.",
        };
    }

    /** How many property profiles the package covers. */
    public function propertyCount(): int
    {
        return match ($this) {
            self::Bronze => 1,
            self::Silver => 2,
            self::Gold   => 3,
        };
    }

    public function propertyAllowance(): string
    {
        return match ($this) {
            self::Bronze => '1 PROPERTY',
            self::Silver => 'UP TO 2 PROPERTIES',
            self::Gold   => 'UP TO 3 PROPERTIES',
        };
    }

    /** @return array{emoji:string,label:string} */
    public function bonusBenefit(): array
    {
        return match ($this) {
            self::Bronze => ['emoji' => '🍽️', 'label' => 'Complimentary Restaurant Discount Incentive'],
            self::Silver => ['emoji' => '✈️',  'label' => 'Complimentary Airfare Incentive'],
            self::Gold   => ['emoji' => '🚢', 'label' => 'Complimentary Cruise Incentive'],
        };
    }

    public function ctaLabel(): string
    {
        return match ($this) {
            self::Bronze => 'GET STARTED',
            self::Silver => 'BOOST MY VISIBILITY',
            self::Gold   => 'GO GOLD',
        };
    }

    // ------------------------------------------------------- feature matrix

    /**
     * The comparison rows, in display order.
     *
     * A dash means the tier does not include the feature — rendered as an
     * explicit "not included" rather than an empty cell, so a blank can never
     * be mistaken for an oversight.
     *
     * @return array<int, array{label:string, values:array<string,string>}>
     */
    public static function comparisonMatrix(): array
    {
        return [
            ['label' => 'Property profiles',        'values' => ['bronze' => '1 property',  'silver' => 'Up to 2 properties', 'gold' => 'Up to 3 properties']],
            ['label' => 'Search visibility',        'values' => ['bronze' => 'Standard',    'silver' => 'Enhanced',           'gold' => 'Premier']],
            ['label' => 'Listing presentation',     'values' => ['bronze' => 'Professional','silver' => 'Enhanced',           'gold' => 'Premium']],
            ['label' => 'Featured listing',         'values' => ['bronze' => '—',           'silver' => '✓',                  'gold' => '✓']],
            ['label' => 'Priority placement',       'values' => ['bronze' => '—',           'silver' => '✓',                  'gold' => 'Highest priority']],
            ['label' => 'Inquiry notifications',    'values' => ['bronze' => 'Standard',    'silver' => 'Priority',           'gold' => 'Priority']],
            ['label' => 'Promotional exposure',     'values' => ['bronze' => 'Standard',    'silver' => 'Enhanced',           'gold' => 'Premium']],
            ['label' => 'Multi-property dashboard', 'values' => ['bronze' => '—',           'silver' => '✓',                  'gold' => '✓']],
            ['label' => 'Featured-area eligibility','values' => ['bronze' => '—',           'silver' => '—',                  'gold' => '✓']],
            ['label' => 'Member support',           'values' => ['bronze' => 'Standard',    'silver' => 'Enhanced',           'gold' => 'Priority']],
        ];
    }

    /** The comparison rows for THIS package, as a feature list. */
    public function features(): array
    {
        $out = [];

        foreach (self::comparisonMatrix() as $row) {
            $value = $row['values'][$this->value] ?? '—';

            $out[] = [
                'label'    => $row['label'],
                'value'    => $value,
                'included' => $value !== '—',
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------- pricing

    /** Settings key holding this package's weekly rate, in cents. */
    public function settingKey(): string
    {
        return "member_services.{$this->value}_price_per_week_cents";
    }

    /** Fallback used when the settings row is missing. */
    public function defaultPricePerWeekCents(): int
    {
        return match ($this) {
            self::Bronze => 24900,   // $249
            self::Silver => 34900,   // $349
            self::Gold   => 44900,   // $449
        };
    }

    /**
     * Current weekly rate in cents, as an integer.
     *
     * Cents throughout — money is never a float. $249.00 stored as 24900
     * multiplies exactly; 249.00 in binary floating point does not.
     */
    public function currentPricePerWeekCents(): int
    {
        $value = setting($this->settingKey(), $this->defaultPricePerWeekCents());

        return max(0, (int) $value);
    }

    /** @return array<int, self> in presentation order. */
    public static function ordered(): array
    {
        return [self::Bronze, self::Silver, self::Gold];
    }
}
