<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * A named set of permissions.
 *
 * The six roles whose `key` matches a UserRole enum value are how the legacy
 * single-role column resolves to permissions — see User::effectiveRoles().
 * Everything else is a custom role a super admin created.
 */
class Role extends Model
{
    use HasFactory;

    /** Cache key for "has RBAC been seeded at all?" — see configured(). */
    private const CONFIGURED_CACHE_KEY = 'rbac:configured';

    /** Cache key prefix for a role's resolved permission keys. */
    private const PERMISSIONS_CACHE_PREFIX = 'rbac:role-permissions:';

    private const CACHE_TTL = 3600;

    protected $fillable = [
        'key', 'name', 'description', 'is_system', 'is_super', 'level',
        'created_by_user_id', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_super' => 'boolean',
            'level' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Any write to a role invalidates the whole RBAC cache. Roles change
        // rarely and permission checks run on every admin request, so a broad
        // flush on write is the right trade.
        static::saved(fn () => static::bustCache());
        static::deleted(fn () => static::bustCache());
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['assigned_by_user_id', 'assigned_at']);
    }

    /** @return list<string> This role's permission keys, cached. */
    public function permissionKeys(): array
    {
        return Cache::remember(
            self::PERMISSIONS_CACHE_PREFIX.$this->getKey(),
            self::CACHE_TTL,
            fn () => $this->permissions()->pluck('key')->all(),
        );
    }

    public function grants(string $permissionKey): bool
    {
        return $this->is_super || in_array($permissionKey, $this->permissionKeys(), true);
    }

    /**
     * Has RBAC been seeded on this environment yet?
     *
     * Permission checks FAIL OPEN for legacy admins while this is false, which
     * is what lets the RBAC tables ship in one deploy and be seeded in the
     * next without locking every admin out of the console in between. It
     * mirrors the fail-open convention in setting() / feature() /
     * PaymentProcessorConfig::isEnabled(). Once RbacSeeder has run, this flips
     * true permanently and granular permissions take over.
     */
    public static function configured(): bool
    {
        try {
            return (bool) Cache::remember(
                self::CONFIGURED_CACHE_KEY,
                self::CACHE_TTL,
                fn () => static::query()->exists(),
            );
        } catch (QueryException) {
            // Table not migrated yet.
            return false;
        }
    }

    public static function bustCache(): void
    {
        Cache::forget(self::CONFIGURED_CACHE_KEY);

        try {
            foreach (static::query()->pluck('id') as $id) {
                Cache::forget(self::PERMISSIONS_CACHE_PREFIX.$id);
            }
        } catch (QueryException) {
            // Nothing to bust if the table isn't there.
        }
    }
}
