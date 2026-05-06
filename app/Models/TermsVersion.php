<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TermsVersion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'kind',
        'version_label',
        'content_hash',
        'content_url',
        'effective_at',
        'superseded_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'superseded_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Content-addressing helper (FR-10.10): given a kind and the canonical text,
     * either return the existing TermsVersion for that hash or insert one.
     * Two calls with identical text yield the same row.
     */
    public static function forContent(string $kind, string $content, string $url, string $versionLabel): self
    {
        $hash = hash('sha256', $content);

        return static::firstOrCreate(
            ['content_hash' => $hash],
            [
                'kind' => $kind,
                'version_label' => $versionLabel,
                'content_url' => $url,
                'effective_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * Most recent effective version for a given kind. Used by the "accept again"
     * middleware in Phase 13.
     */
    public static function currentFor(string $kind): ?self
    {
        return static::where('kind', $kind)
            ->whereNull('superseded_at')
            ->orderByDesc('effective_at')
            ->first();
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(TermsAcceptance::class, 'terms_version_id');
    }
}
