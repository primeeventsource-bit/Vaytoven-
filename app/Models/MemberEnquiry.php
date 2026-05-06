<?php

namespace App\Models;

use App\Enums\MemberEnquiryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MemberEnquiry extends Model
{
    use HasFactory;

    protected $table = 'members_enquiries';

    protected $fillable = [
        'reference',
        'first_name',
        'last_name',
        'email',
        'phone',
        'club',
        'property',
        'points',
        'contact_window',
        'status',
        'consented_at',
        'source_url',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
        'status'       => MemberEnquiryStatus::class,
    ];

    protected static function booted(): void
    {
        // Generate a public-facing reference at create time if the caller
        // didn't supply one. Done here (not in the controller) so factories,
        // seeders, and any future ingestion path get the same guarantee.
        static::creating(function (MemberEnquiry $enquiry) {
            if (empty($enquiry->reference)) {
                $enquiry->reference = self::generateReference();
            }
        });
    }

    public static function generateReference(): string
    {
        return 'VYT-'.strtoupper(Str::random(8));
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
