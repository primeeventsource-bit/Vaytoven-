<?php

namespace App\Models;

use App\Enums\PaymentProcessor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostPayoutAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_id',
        'processor',
        'external_account_id',
        'status',
        'payouts_enabled',
        'charges_enabled',
        'last_synced_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'processor' => PaymentProcessor::class,
            'payouts_enabled' => 'boolean',
            'charges_enabled' => 'boolean',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }
}
