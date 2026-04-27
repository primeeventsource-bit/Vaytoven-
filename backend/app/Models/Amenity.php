<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Amenity extends Model
{
    protected $fillable = ['slug', 'name', 'category', 'icon', 'is_safety'];
    protected $casts = ['is_safety' => 'boolean'];

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_amenities');
    }
}
