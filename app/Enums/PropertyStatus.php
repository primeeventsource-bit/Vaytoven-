<?php

namespace App\Enums;

enum PropertyStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';

    public function isPublic(): bool
    {
        return $this === self::Active;
    }
}
