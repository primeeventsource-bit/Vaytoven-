<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A single granular capability, e.g. `properties.publish`.
 *
 * Rows are a synced mirror of App\Support\PermissionCatalog — never create
 * them by hand. Add the key to the catalog and run RbacSeeder.
 */
class Permission extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'module', 'label', 'description'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
