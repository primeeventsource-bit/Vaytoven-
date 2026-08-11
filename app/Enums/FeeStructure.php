<?php

namespace App\Enums;

/**
 * Which side of the transaction carries the Vaytoven service fee.
 *
 * Split-Fee   host pays a small commission, guest pays a visible service fee.
 * Single-Fee  host carries the whole fee, guest sees no separate service fee
 *             and their total equals the stay price.
 */
enum FeeStructure: string
{
    case Split = 'split';
    case Single = 'single';

    public function label(): string
    {
        return match ($this) {
            self::Split => 'Split-Fee',
            self::Single => 'Single-Fee',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Split => 'Host pays a lower commission; the guest pays a separate Vaytoven Guest Service Fee shown at checkout.',
            self::Single => 'Host carries the whole Vaytoven Service Fee. The guest pays the stay price with no separate service fee.',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
