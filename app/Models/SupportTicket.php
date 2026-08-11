<?php

namespace App\Models;

use App\Enums\SupportCategory;
use App\Support\Reference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasFactory;

    /** Opened by the AI support agent. */
    public const SOURCE_CHAT = 'chat';

    /** Submitted through the public /trip-support form. */
    public const SOURCE_TRIP_SUPPORT = 'trip_support';

    protected $fillable = [
        'reference',
        'source',
        'session_id',
        'opened_by_user_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'property_reference',
        'category',
        'subject',
        'body',
        'status',
        'priority',
        'assigned_to_user_id',
        'assigned_at',
        'resolved_at',
        'resolution_note',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'category' => SupportCategory::class,
            'assigned_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Every ticket gets a quotable reference from here on, including ones
        // the chat agent opens — a customer who was told "we've raised a
        // ticket" should be able to quote something back.
        static::creating(function (self $ticket) {
            $ticket->reference ??= Reference::generate(
                'S',
                fn (string $code) => static::query()->where('reference', $code)->exists(),
            );
        });
    }

    /** Who to reply to: the signed-in account if there was one, else the form. */
    public function replyToEmail(): ?string
    {
        return $this->contact_email ?: $this->openedBy?->email;
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SupportChatSession::class, 'session_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id')->orderBy('occurred_at');
    }
}
