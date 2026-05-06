<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'role',
        'content',
        'tool_calls',
        'tool_result',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'tool_result' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SupportChatSession::class, 'session_id');
    }
}
