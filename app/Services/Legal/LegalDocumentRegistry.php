<?php

namespace App\Services\Legal;

use App\Models\TermsVersion;
use Illuminate\Support\Facades\View;

/**
 * Source of truth for the four legal documents Vaytoven publishes.
 *
 * Each document maps to:
 *   - kind          → enum value used on terms_versions.kind
 *   - view          → the Blade template the public legal page renders
 *   - route         → named route the URL is generated from (resolves to public_url())
 *   - version_label → human-readable label baked into the rendered HTML
 *
 * The registry's job is to:
 *   1. Resolve the *canonical text* of each document — the rendered <main>
 *      region, not the source Blade and not the whole page. What users read
 *      is what gets hashed; the surrounding chrome (CSRF token, absolute
 *      URLs) is excluded because it varies by environment and session
 *      without the agreement changing. See canonicalText().
 *   2. Idempotently materialise terms_versions rows via TermsVersion::forContent
 *      so the deploy seeder is safe to re-run on every push.
 *   3. Tell callers which versions are "current" for each kind, used by the
 *      register form, the EnsureCurrentTermsAccepted middleware, and the
 *      JSON versions endpoint.
 *
 * When counsel hands over the binding text, the placeholder Blade content is
 * replaced; the next deploy materialises a *new* TermsVersion row (different
 * SHA-256), and existing users will be required to re-accept.
 */
class LegalDocumentRegistry
{
    public const KIND_TOS = 'tos';
    public const KIND_PRIVACY = 'privacy';
    public const KIND_MEMBER_AGREEMENT = 'member_agreement';

    /**
     * Documents required to be accepted at registration. Privacy is
     * informational under most regimes (you can't make someone "accept" the
     * fact that you process their data — you have to be transparent about
     * it), but we record acceptance anyway so the audit trail is uniform.
     */
    public const REGISTRATION_REQUIRED = [
        self::KIND_TOS,
        self::KIND_PRIVACY,
    ];

    /** @var array<int, array<string, string>> */
    private const DOCUMENTS = [
        [
            'kind'          => self::KIND_TOS,
            'view'          => 'legal.tos',
            'route'         => 'legal.tos',
            'version_label' => 'v1',
        ],
        [
            'kind'          => self::KIND_PRIVACY,
            'view'          => 'legal.privacy',
            'route'         => 'legal.privacy',
            'version_label' => 'v1',
        ],
        [
            'kind'          => self::KIND_MEMBER_AGREEMENT,
            'view'          => 'legal.member-agreement',
            'route'         => 'legal.member-agreement',
            'version_label' => 'v1',
        ],
    ];

    /**
     * Materialise (or refresh) all 4 documents into terms_versions.
     * Idempotent — re-running with unchanged content is a no-op.
     *
     * @return array<int, TermsVersion>
     */
    public function materialiseAll(): array
    {
        return array_map(fn (array $d) => $this->materialise($d), self::DOCUMENTS);
    }

    /**
     * Returns the current TermsVersion for each registered kind, in registration order.
     *
     * @return array<string, TermsVersion>  keyed by kind
     */
    public function currentVersions(): array
    {
        $out = [];
        foreach (self::DOCUMENTS as $doc) {
            $version = TermsVersion::currentFor($doc['kind']);
            if ($version) {
                $out[$doc['kind']] = $version;
            }
        }
        return $out;
    }

    /**
     * Returns the documents required at registration as TermsVersion rows.
     *
     * @return array<int, TermsVersion>
     */
    public function registrationRequired(): array
    {
        $current = $this->currentVersions();
        $required = [];
        foreach (self::REGISTRATION_REQUIRED as $kind) {
            if (isset($current[$kind])) {
                $required[] = $current[$kind];
            }
        }
        return $required;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function documents(): array
    {
        return self::DOCUMENTS;
    }

    private function materialise(array $doc): TermsVersion
    {
        $rendered = View::make($doc['view'])->render();
        $url = route($doc['route']);

        return TermsVersion::forContent(
            kind:         $doc['kind'],
            content:      $this->canonicalText($rendered),
            url:          $url,
            versionLabel: $doc['version_label'],
        );
    }

    /**
     * The part of the rendered page that IS the agreement — the <main> region
     * holding the title, effective date, version label, and body.
     *
     * Hashing the whole page looks more honest but is not: the surrounding
     * chrome carries a per-session CSRF token and absolute route() URLs built
     * from APP_URL. That made the hash environment-coupled — the same document
     * hashed differently on each environment — and it meant that pointing the
     * app at a real domain would silently mint a new version and force every
     * existing user to re-accept terms that had not changed a word.
     *
     * The nav bar is not part of the contract. The document is.
     */
    private function canonicalText(string $renderedPage): string
    {
        $matched = preg_match(
            '#<main class="legal-shell">(.*?)</main>#s',
            $renderedPage,
            $matches,
        );

        // Fail loudly rather than falling back to the full page: a silent
        // fallback would reintroduce the environment coupling exactly when
        // someone changes the layout, which is when nobody is looking for it.
        if ($matched !== 1) {
            throw new \RuntimeException(
                'Legal document layout no longer exposes <main class="legal-shell">; '
                .'cannot compute a stable content hash. Update '.self::class.'::canonicalText().'
            );
        }

        return trim($matches[1]);
    }
}
