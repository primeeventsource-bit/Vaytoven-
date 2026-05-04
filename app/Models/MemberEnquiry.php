<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberEnquiry extends Model
{
    use HasFactory;

    protected $table = 'members_enquiries';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'club',
        'property',
        'points',
        'contact_window',
        'consented_at',
        'source_url',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
    ];
}
