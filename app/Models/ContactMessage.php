<?php

namespace App\Models;

use App\Enums\ContactDepartment;
use App\Support\Reference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A submission from /contact. Every message is retained — the department is a
 * routing hint, not a filter that discards anything.
 */
class ContactMessage extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_HANDLED = 'handled';

    protected $fillable = [
        'reference', 'first_name', 'last_name', 'email', 'phone',
        'department', 'subject', 'message', 'status',
        'handled_by_user_id', 'handled_at',
        'source_url', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'department' => ContactDepartment::class,
            'handled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $message) {
            $message->reference ??= Reference::generate(
                'C',
                fn (string $code) => static::query()->where('reference', $code)->exists(),
            );
        });
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }

    public function name(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
