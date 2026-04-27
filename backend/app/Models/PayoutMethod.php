<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutMethod extends Model
{
    protected $fillable = [
        'user_id', 'stripe_account_id',
        'charges_enabled', 'payouts_enabled', 'details_submitted',
        'requirements_due', 'country_code', 'currency', 'is_default',
    ];

    protected $casts = [
        'charges_enabled'   => 'boolean',
        'payouts_enabled'   => 'boolean',
        'details_submitted' => 'boolean',
        'is_default'        => 'boolean',
        'requirements_due'  => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
