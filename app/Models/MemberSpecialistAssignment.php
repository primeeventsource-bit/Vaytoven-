<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberSpecialistAssignment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'specialist_user_id',
        'enquiry_id',
        'assigned_by_user_id',
        'assignment_method',
        'assigned_at',
        'unassigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'specialist_user_id');
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(MemberEnquiry::class, 'enquiry_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
