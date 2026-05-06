<?php

namespace App\Models;

use App\Enums\Surface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginSession extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'auth_event',
        'surface',
        'session_id',
        'ip_address',
        'country',
        'region',
        'city',
        'latitude',
        'longitude',
        'asn',
        'is_vpn',
        'is_tor',
        'is_datacenter',
        'device_type',
        'os',
        'browser',
        'user_agent',
        'is_suspicious',
        'suspicious_reasons',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'surface' => Surface::class,
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'is_vpn' => 'boolean',
            'is_tor' => 'boolean',
            'is_datacenter' => 'boolean',
            'is_suspicious' => 'boolean',
            'suspicious_reasons' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
