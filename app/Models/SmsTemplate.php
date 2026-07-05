<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versioned SMS template — same immutable-version model as EmailTemplate.
 */
class SmsTemplate extends Model
{
    protected $fillable = [
        'key', 'name', 'body_text', 'version', 'active', 'variables', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'active' => 'boolean',
            'variables' => 'array',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** The live version for a template key, or null. */
    public static function activeFor(string $key): ?self
    {
        return static::query()
            ->where('key', $key)
            ->where('active', true)
            ->orderByDesc('version')
            ->first();
    }
}
