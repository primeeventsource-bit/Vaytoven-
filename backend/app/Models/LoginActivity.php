<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginActivity extends Model
{
    protected $table = 'login_activity';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'ip_address', 'country_code', 'region', 'city',
        'latitude', 'longitude', 'user_agent', 'device_type', 'os', 'browser',
        'event', 'was_suspicious', 'suspicious_reasons', 'created_at',
    ];

    protected $casts = [
        'was_suspicious'      => 'boolean',
        'suspicious_reasons'  => 'array',
        'created_at'          => 'datetime',
        'latitude'            => 'float',
        'longitude'           => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
