<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Newsletter subscriber. Distinct from User — a Subscriber doesn't have an
 * account on the platform; they've just opted in to marketing updates.
 *
 * Email is the unique key. Re-submitting the same address from the /signup
 * form is an idempotent updateOrCreate (refreshes phone, source_url, ip,
 * user_agent) so duplicate clicks don't error out.
 */
class Subscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'status',
        'unsubscribed_at',
        'source_url',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'unsubscribed_at' => 'datetime',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';
}
