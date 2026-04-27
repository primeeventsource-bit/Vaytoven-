<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'thread_id', 'sender_id', 'body', 'attachments', 'read_at', 'flagged',
    ];

    protected $casts = [
        'attachments' => 'array',
        'read_at'     => 'datetime',
        'flagged'     => 'boolean',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
