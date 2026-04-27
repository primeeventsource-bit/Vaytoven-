<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'uuid', 'email', 'phone', 'password_hash',
        'first_name', 'last_name', 'display_name', 'avatar_url', 'bio', 'date_of_birth',
        'locale', 'currency', 'is_host', 'is_superhost',
        'two_factor_enabled', 'marketing_opt_in', 'status',
    ];

    protected $hidden = [
        'password_hash', 'two_factor_secret', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at'    => 'datetime',
        'phone_verified_at'    => 'datetime',
        'govt_id_verified_at'  => 'datetime',
        'last_login_at'        => 'datetime',
        'locked_until'         => 'datetime',
        'privacy_consent_at'   => 'datetime',
        'date_of_birth'        => 'date',
        'is_host'              => 'boolean',
        'is_superhost'         => 'boolean',
        'two_factor_enabled'   => 'boolean',
        'marketing_opt_in'     => 'boolean',
    ];

    /**
     * Sanctum + Laravel auth expect the password to come from `password`.
     * We store it as `password_hash` to keep it out of accidental serialization.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    // ─── Relationships ────────────────────────────────────────────

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['granted_at', 'granted_by']);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'host_id');
    }

    public function bookingsAsGuest(): HasMany
    {
        return $this->hasMany(Booking::class, 'guest_id');
    }

    public function bookingsAsHost(): HasMany
    {
        return $this->hasMany(Booking::class, 'host_id');
    }

    public function payoutMethods(): HasMany
    {
        return $this->hasMany(PayoutMethod::class);
    }

    public function loginActivity(): HasMany
    {
        return $this->hasMany(LoginActivity::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->hasRole('super_admin');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function activePayoutMethod(): ?PayoutMethod
    {
        return $this->payoutMethods()
            ->where('is_default', true)
            ->where('payouts_enabled', true)
            ->first();
    }

    /**
     * Mask email for non-owner views.
     */
    public function maskedEmail(): string
    {
        $parts = explode('@', $this->email);
        if (count($parts) !== 2) {
            return $this->email;
        }
        $local = $parts[0];
        $masked = substr($local, 0, 1) . str_repeat('•', max(1, strlen($local) - 1));
        return $masked . '@' . $parts[1];
    }
}
