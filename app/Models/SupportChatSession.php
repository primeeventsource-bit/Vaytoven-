<?php

namespace App\Models;

use App\Enums\Surface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportChatSession extends Model
{
    use HasFactory;

    protected $table = 'support_chat_sessions';

    protected $fillable = [
        'user_id',
        'visitor_id',
        'surface',
        'claude_model',
        'system_prompt_version',
        'started_at',
        'ended_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'surface' => Surface::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'session_id')->orderBy('occurred_at');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'session_id');
    }
}
