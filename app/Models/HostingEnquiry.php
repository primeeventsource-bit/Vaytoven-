<?php

namespace App\Models;

use App\Support\Reference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A property owner asking to have their property advertised on Vaytoven.
 *
 * Note what this model does NOT hold: no bank details, no government ID, no
 * tax identifiers. Advertising a listing needs none of it, and a public form
 * should not invite people to send it.
 */
class HostingEnquiry extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_HANDLED = 'handled';

    public const KIND_PROPERTY = 'property';

    public const KIND_RESORT = 'resort';

    protected $fillable = [
        'reference', 'listing_kind', 'user_id', 'first_name', 'last_name', 'email', 'phone',
        'property_name', 'property_type', 'resort_name', 'club_or_developer', 'ownership_details',
        'city', 'region', 'country',
        'bedrooms', 'bathrooms', 'indicative_nightly_cents', 'availability',
        'message', 'status', 'handled_by_user_id', 'handled_at',
        'source_url', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'indicative_nightly_cents' => 'integer',
            'handled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $enquiry) {
            $enquiry->reference ??= Reference::generate(
                'H',
                fn (string $code) => static::query()->where('reference', $code)->exists(),
            );
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }

    public function name(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** "Zermatt, Valais, Switzerland" — skipping the parts we weren't given. */
    public function location(): string
    {
        return implode(', ', array_filter([$this->city, $this->region, $this->country])) ?: '—';
    }

    public function isResort(): bool
    {
        return $this->listing_kind === self::KIND_RESORT;
    }

    /** Whichever name applies to this submission's kind. */
    public function displayName(): string
    {
        return ($this->isResort() ? $this->resort_name : $this->property_name) ?: '—';
    }
}
