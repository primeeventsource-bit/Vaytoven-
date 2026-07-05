<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Simple lookup: kinds of property (villa, condo, cabin, …). Admin-managed.
 */
class PropertyType extends Model
{
    protected $fillable = [
        'slug', 'label', 'icon', 'active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
