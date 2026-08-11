<?php

namespace App\Models;

use App\Enums\FeeStructure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Vaytoven service fee configuration, optionally scoped to a host, a
 * property, or a class of listings.
 *
 * Rates are basis points throughout — see the migration for why.
 */
class ServiceFeeConfig extends Model
{
    use HasFactory;

    public const SCOPE_DEFAULT = 'default';

    public const SCOPE_LISTING_SOURCE = 'listing_source';

    public const SCOPE_HOST = 'host';

    public const SCOPE_PROPERTY = 'property';

    /**
     * The guest band the requirement fixes. Enforced in validation AND here,
     * so a config written by a seeder or a console command cannot slip a rate
     * outside the band past the admin form.
     */
    public const GUEST_BPS_MIN = 1410;   // 14.1%

    public const GUEST_BPS_MAX = 1650;   // 16.5%

    /**
     * Most specific wins. Adding a dimension later means inserting it here and
     * seeding rows — no schema change.
     */
    public const SCOPE_PRECEDENCE = [
        self::SCOPE_PROPERTY => 40,
        self::SCOPE_HOST => 30,
        self::SCOPE_LISTING_SOURCE => 20,
        self::SCOPE_DEFAULT => 10,
    ];

    protected $fillable = [
        'name', 'scope_type', 'scope_value', 'fee_structure',
        'split_host_bps', 'split_guest_bps', 'single_host_bps',
        'effective_from', 'effective_to', 'active', 'notes', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'fee_structure' => FeeStructure::class,
            'split_host_bps' => 'integer',
            'split_guest_bps' => 'integer',
            'single_host_bps' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /** Active, and inside its effective window right now. */
    public function scopeInEffect(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        $at ??= now();

        return $query->where('active', true)
            ->where(fn (Builder $q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $at));
    }

    /** The host's rate under this config's structure. */
    public function hostBps(): int
    {
        return $this->fee_structure === FeeStructure::Single
            ? $this->single_host_bps
            : $this->split_host_bps;
    }

    /** The guest's rate. Single-Fee means the guest pays no service fee. */
    public function guestBps(): int
    {
        return $this->fee_structure === FeeStructure::Single ? 0 : $this->split_guest_bps;
    }

    public function precedence(): int
    {
        return self::SCOPE_PRECEDENCE[$this->scope_type] ?? 0;
    }

    public function scopeLabel(): string
    {
        return match ($this->scope_type) {
            self::SCOPE_DEFAULT => 'Platform default',
            self::SCOPE_LISTING_SOURCE => 'Listing source: '.$this->scope_value,
            self::SCOPE_HOST => 'Host #'.$this->scope_value,
            self::SCOPE_PROPERTY => 'Property #'.$this->scope_value,
            default => ucfirst($this->scope_type),
        };
    }
}
