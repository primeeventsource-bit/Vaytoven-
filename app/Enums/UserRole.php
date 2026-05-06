<?php

namespace App\Enums;

enum UserRole: string
{
    case Traveler = 'traveler';
    case Host = 'host';
    case Member = 'member';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public function isAdmin(): bool
    {
        return match ($this) {
            self::Admin, self::SuperAdmin => true,
            default => false,
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }
}
