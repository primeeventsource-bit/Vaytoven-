<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PpcVisitor extends Model
{
    use HasFactory;

    protected $table = 'ppc_visitors';

    protected $fillable = [
        'visitor_id',
        'first_seen_at',
        'landing_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'fbclid',
        'referrer',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
        ];
    }
}
