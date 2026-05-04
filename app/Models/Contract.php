<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasFactory;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SENT      = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_VIEWED    = 'viewed';
    public const STATUS_SIGNED    = 'signed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DECLINED  = 'declined';
    public const STATUS_VOIDED    = 'voided';
    public const STATUS_EXPIRED   = 'expired';

    public const SOURCE_WEB   = 'web';
    public const SOURCE_APP   = 'app';
    public const SOURCE_ADMIN = 'admin';

    public const TYPE_HOST_LISTING   = 'host_listing';
    public const TYPE_MEMBER_PROGRAM = 'member_program';
    public const TYPE_BOOKING_TERMS  = 'booking_terms';
    public const TYPE_CUSTOM         = 'custom';

    protected $fillable = [
        'user_id',
        'client_name',
        'client_email',
        'client_phone',
        'contract_type',
        'title',
        'template_id',
        'envelope_id',
        'status',
        'source',
        'payment_id',
        'terms_accepted_at',
        'sent_at',
        'viewed_at',
        'signed_at',
        'completed_at',
        'declined_at',
        'voided_at',
        'expires_at',
        'signed_pdf_path',
        'certificate_pdf_path',
        'last_signer_ip',
        'last_signer_user_agent',
    ];

    protected $casts = [
        'terms_accepted_at' => 'datetime',
        'sent_at'           => 'datetime',
        'viewed_at'         => 'datetime',
        'signed_at'         => 'datetime',
        'completed_at'      => 'datetime',
        'declined_at'       => 'datetime',
        'voided_at'         => 'datetime',
        'expires_at'        => 'datetime',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(ContractEvent::class)->orderBy('occurred_at');
    }

    public function user()
    {
        // Return null association if the User model isn't yet wired up.
        // Once auth scaffolding lands, this becomes belongsTo(User::class).
        return $this->belongsTo(\App\Models\User::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_DECLINED,
            self::STATUS_VOIDED,
            self::STATUS_EXPIRED,
        ], true);
    }

    public function isSignable(): bool
    {
        return in_array($this->status, [
            self::STATUS_SENT,
            self::STATUS_DELIVERED,
            self::STATUS_VIEWED,
        ], true);
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn () => ucfirst(str_replace('_', ' ', (string) $this->status)));
    }

    public function scopeForClient($query, string $email)
    {
        return $query->where('client_email', $email);
    }

    public function scopeWithStatus($query, string|array $status)
    {
        return is_array($status) ? $query->whereIn('status', $status) : $query->where('status', $status);
    }
}
