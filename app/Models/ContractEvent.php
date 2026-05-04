<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractEvent extends Model
{
    use HasFactory;

    public const EVENT_SENT          = 'sent';
    public const EVENT_DELIVERED     = 'delivered';
    public const EVENT_VIEWED        = 'viewed';
    public const EVENT_SIGNED        = 'signed';
    public const EVENT_COMPLETED     = 'completed';
    public const EVENT_DECLINED      = 'declined';
    public const EVENT_VOIDED        = 'voided';
    public const EVENT_AUTH_FAILED   = 'authentication_failed';
    public const EVENT_REASSIGNED    = 'reassigned';
    public const EVENT_RESENT        = 'resent';

    protected $fillable = [
        'contract_id',
        'event_type',
        'occurred_at',
        'recipient_id',
        'recipient_email',
        'ip_address',
        'user_agent',
        'raw_payload',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
