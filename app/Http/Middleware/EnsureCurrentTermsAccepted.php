<?php

namespace App\Http\Middleware;

use App\Models\TermsAcceptance;
use App\Services\Legal\LegalDocumentRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the signed-in user has accepted the current version of every
 * registration-required legal document (FR-13).
 *
 * Protects authenticated app routes after counsel publishes amended terms:
 * the deploy seed materialises a new TermsVersion row with a different hash,
 * existing users no longer have an acceptance for the *current* version,
 * and this middleware redirects them to /legal/review-and-accept.
 *
 * Allowlisted routes (the legal review page itself, the POST that records
 * acceptance, and the auth-logout path) are skipped so the user can always
 * either accept the new terms or leave the application.
 */
class EnsureCurrentTermsAccepted
{
    public function __construct(private readonly LegalDocumentRegistry $registry)
    {
    }

    /**
     * Routes the user is allowed to hit even with stale acceptance.
     */
    private const ALLOWLIST = [
        'legal.review-and-accept',
        'legal.review-and-accept.submit',
        'legal.tos',
        'legal.privacy',
        'legal.chargeback',
        'legal.member-agreement',
        'legal.versions',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if ($routeName && in_array($routeName, self::ALLOWLIST, true)) {
            return $next($request);
        }

        $required = $this->registry->registrationRequired();
        if ($required === []) {
            // Registry is empty (fresh DB before seed). Don't lock the user
            // out for an empty source of truth — allow through.
            return $next($request);
        }

        $missingCount = TermsAcceptance::where('user_id', $user->id)
            ->whereIn('terms_version_id', collect($required)->pluck('id')->all())
            ->count();

        if ($missingCount >= count($required)) {
            return $next($request);
        }

        return redirect()->route('legal.review-and-accept');
    }
}
