<?php

namespace App\Models;

use App\Enums\FeeStructure;
use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'role',
        // Host-level Split-Fee / Single-Fee override. Without this in
        // $fillable a create()/update() would silently discard it.
        'fee_structure',
        'deactivated_at',
        'deactivated_by_user_id',
        'created_by_user_id',
        'last_login_at',
        // Set when staff issue a password, cleared when the account holder
        // replaces it. Without this in $fillable a create() would silently
        // drop it and the forced change would never happen.
        'must_change_password',
        'password_changed_at',
        // Free-text staff notes on the member profile. Without this in
        // $fillable, update() drops it and the form appears to save while
        // changing nothing.
        'staff_notes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'role' => UserRole::Traveler->value,
    ];

    /** Memoized result of effectiveRoles() — not an Eloquent attribute. */
    private ?Collection $resolvedRoles = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            // Cast so the attribute is a FeeStructure here as it is on Booking
            // and ServiceFeeConfig, rather than a bare string on this model
            // only. Nullable: most users have no override.
            'fee_structure' => FeeStructure::class,
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    /** Active = `deactivated_at` is null. Deactivated users still exist as rows. */
    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * Compose the display `name` from its parts.
     *
     * `name` stays the authoritative display value everywhere (certificates,
     * admin tables, PDFs), so the registration form collects the parts and
     * calls this rather than every read site learning to concatenate.
     */
    public static function composeName(string $firstName, ?string $lastName = null): string
    {
        return trim($firstName.' '.trim((string) $lastName));
    }

    /** Preferred greeting: first name if we have one, otherwise the full name. */
    public function firstNameOrName(): string
    {
        return $this->first_name ?: $this->name;
    }

    /**
     * How an owner is named on a public listing: "John S.".
     *
     * A listing is an advertisement for a property that in many cases somebody
     * still lives in or holidays at, and it already carries a location, dates
     * the place is empty, and a way to contact whoever holds it. A full surname
     * on top of that is the piece that makes the set identifying, and it buys
     * the reader nothing — the point is to feel dealt with by a person, not to
     * know which person.
     *
     * Falls back rather than exposing more: a record with only a full name in
     * one field is split on whitespace, and one with nothing usable returns a
     * neutral label instead of an empty line or an email address.
     */
    public function publicDisplayName(): string
    {
        $first = trim((string) $this->first_name);
        $last  = trim((string) $this->last_name);

        if ($first === '' && $last === '') {
            // Legacy rows kept a single `name`. Split it rather than printing
            // the whole thing, which is what this method exists to avoid.
            $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
            $first = $parts[0] ?? '';
            $last  = count($parts) > 1 ? (string) end($parts) : '';
        }

        if ($first === '') {
            return 'Property owner';
        }

        // mb_substr, not substr: a name beginning with a multi-byte character
        // would otherwise be cut mid-character and render as a replacement box.
        $initial = $last !== '' ? ' '.mb_strtoupper(mb_substr($last, 0, 1)).'.' : '';

        return $first.$initial;
    }
    /** Returns true if the user has any role at or above admin. */
    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isMemberSpecialist();
    }

    public function isAdmin(): bool
    {
        return $this->role?->isAdmin() ?? false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->isSuperAdmin() ?? false;
    }

    public function isMemberSpecialist(): bool
    {
        return $this->role?->isMemberSpecialist() ?? false;
    }

    /** Roles attached explicitly through `role_user` (in addition to `role`). */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['assigned_by_user_id', 'assigned_at']);
    }

    /**
     * Every role that grants this user permissions: the system role matching
     * the primary `role` enum column, plus any explicitly attached roles.
     *
     * Memoized per instance — permission checks run several times per request.
     *
     * @return \Illuminate\Support\Collection<int, Role>
     */
    public function effectiveRoles(): Collection
    {
        if ($this->resolvedRoles !== null) {
            return $this->resolvedRoles;
        }

        $attached = $this->relationLoaded('roles') ? $this->roles : $this->roles()->get();

        // The primary role column resolves to the system role of the same key.
        $primary = $this->role
            ? Role::query()->where('key', $this->role->value)->first()
            : null;

        // Build a new collection rather than push()ing — $attached may be the
        // loaded `roles` relation, which must not gain a phantom member.
        $all = $primary && ! $attached->contains('id', $primary->id)
            ? $attached->concat([$primary])
            : collect($attached);

        return $this->resolvedRoles = $all;
    }

    /** @return list<string> Deduplicated permission keys across all effective roles. */
    public function permissionKeys(): array
    {
        return array_values(array_unique(
            $this->effectiveRoles()->flatMap->permissionKeys()->all()
        ));
    }

    /**
     * Does this user hold a given granular permission?
     *
     * Super admins short-circuit to true. While RBAC is unseeded the check
     * falls back to the legacy binary gate so admins keep working — see
     * Role::configured().
     */
    public function hasPermission(string $permissionKey): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! Role::configured()) {
            return $this->isAdmin();
        }

        foreach ($this->effectiveRoles() as $role) {
            if ($role->grants($permissionKey)) {
                return true;
            }
        }

        return false;
    }

    public function hasRole(string $roleKey): bool
    {
        return $this->effectiveRoles()->contains('key', $roleKey);
    }

    /**
     * Highest privilege level this user holds. A user may never create,
     * edit, or assign a role at or above their own level — that's what stops
     * an Admin from minting themselves a Super Admin equivalent.
     */
    public function roleLevel(): int
    {
        if ($this->isSuperAdmin()) {
            return PHP_INT_MAX;
        }

        return (int) ($this->effectiveRoles()->max('level') ?? 0);
    }

    /** Drop the memoized role set after a role assignment changes. */
    public function forgetEffectiveRoles(): void
    {
        $this->resolvedRoles = null;
        $this->unsetRelation('roles');
    }

    public function hostProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'host_id');
    }

    public function loginSessions(): HasMany
    {
        return $this->hasMany(LoginSession::class);
    }
}
