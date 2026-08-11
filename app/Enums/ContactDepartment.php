<?php

namespace App\Enums;

/**
 * Routing target for a /contact submission. Kept in code rather than a DB enum
 * so adding a department is a one-line change with no migration.
 */
enum ContactDepartment: string
{
    case General = 'general';
    case Membership = 'membership';
    case Listings = 'listings';
    case VacationClub = 'vacation_club';
    case Technical = 'technical';
    case Billing = 'billing';
    case Business = 'business';
    case Media = 'media';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General Support',
            self::Membership => 'Membership',
            self::Listings => 'Property Listings',
            self::VacationClub => 'Vacation Club',
            self::Technical => 'Technical Support',
            self::Billing => 'Billing',
            self::Business => 'Business Inquiries',
            self::Media => 'Media',
        };
    }

    /** @return array<string, string> value => label, for a <select>. */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
