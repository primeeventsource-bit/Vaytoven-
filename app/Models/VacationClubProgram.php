<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vacation club / points program a member can name on an MLP enquiry
 * (Marriott Vacation Club, Hilton Grand Vacations, …). Admin-extensible.
 */
class VacationClubProgram extends Model
{
    protected $fillable = [
        'name', 'code', 'points_based', 'active', 'enquiry_notes', 'sort_order', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'points_based' => 'boolean',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
