<?php

namespace App\Models;

use App\Services\Legal\LegalDocumentRegistry;
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
     * Make this the one version of its kind in force, and retire the rest.
     *
     * Only supersession bookkeeping is written. kind, version_label,
     * content_hash, content_url and effective_at are never touched on any row,
     * because somebody accepted those exact terms at that exact moment and
     * that record has to stay as it was.
     *
     * effective_at deliberately keeps meaning "when this text FIRST took
     * effect", so re-instating a previously retired version does not rewrite
     * its history. That is also why currentFor() leans on superseded_at rather
     * than on ordering — see the note there.
     */
    public function markAsTheCurrentVersion(): void
    {
        if ($this->superseded_at !== null) {
            // A revert: this exact text is in force again.
            $this->forceFill(['superseded_at' => null])->save();
        }

        static::where('kind', $this->kind)
            ->whereKeyNot($this->getKey())
            ->whereNull('superseded_at')
            ->update(['superseded_at' => now()]);
    }

    /**
     * The version of a given kind currently in force.
     *
     * superseded_at is the authority here, not the effective_at ordering.
     * Ordering alone gets the wrong answer the moment a document is reverted
     * to earlier text: content-addressing returns the ORIGINAL row, whose
     * effective_at is older than the text it replaced, so "latest effective"
     * would name a version the site is no longer serving — and the
     * re-acceptance middleware would then ask people to accept a document
     * that is not the one on screen. The ordering stays only as a tiebreak
     * for rows predating the invariant.
     */
    public static function currentFor(string $kind): ?self
    {
        return static::where('kind', $kind)
            ->whereNull('superseded_at')
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Where a user should be sent to READ this document, right now.
     *
     * Not content_url. That column records the absolute URL the text was
     * published at when the version was materialised, which is a useful audit
     * fact and a terrible link: the seeder runs on Laravel Cloud, so it froze
     * `https://v-app-dev-main-oyo1n9.laravel.cloud/legal/tos` into the row.
     * The register form and the re-acceptance page rendered that value
     * verbatim, so a user on vaytoven.com clicking "Terms" was sent to an
     * internal vanity hostname — infrastructure disclosure, and it reads
     * exactly like a phishing redirect at the moment you are asking someone to
     * agree to a contract.
     *
     * Resolved from the route each request, so it always matches the host the
     * user is actually on. Falls back to the recorded URL for a kind the
     * registry no longer knows about.
     */
    public function publicUrl(): string
    {
        $route = LegalDocumentRegistry::routeNameFor($this->kind);

        return $route ? route($route) : (string) $this->content_url;
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(TermsAcceptance::class, 'terms_version_id');
    }
}
