<?php

namespace App\Enums;

/**
 * Audience tag for help articles. Mirrors the three landing-page audiences so
 * search results, the index page, and the chat tool can scope by persona.
 */
enum HelpAudience: string
{
    case Traveler = 'traveler';
    case Host = 'host';
    case Member = 'member';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Traveler => 'Travelers',
            self::Host     => 'Hosts',
            self::Member   => 'Members',
            self::All      => 'Everyone',
        };
    }
}
