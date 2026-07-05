<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versioned email template. Rows are immutable once created — an edit
 * inserts (key, version+1) and flips `active`; sent mail can always be
 * traced back to the exact copy that was live at send time.
 */
class EmailTemplate extends Model
{
    protected $fillable = [
        'key', 'name', 'subject', 'body_markdown', 'version', 'active', 'variables', 'updated_by',
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
