<?php

namespace App\Http\Controllers;

use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function __construct(private readonly LegalDocumentRegistry $registry)
    {
    }

    public function tos(): View
    {
        return view('legal.tos');
    }

    public function privacy(): View
    {
        return view('legal.privacy');
    }

    public function memberAgreement(): View
    {
        return view('legal.member-agreement');
    }

    /**
     * Public JSON: which versions are currently in force per kind.
     * Used by the chat agent's regulatory answer surface and by external
     * audits checking that what's published matches what the database
     * thinks is current.
     */
    public function versions(): JsonResponse
    {
        $payload = [];
        foreach ($this->registry->currentVersions() as $kind => $version) {
            $payload[] = [
                'kind'          => $kind,
                'version_label' => $version->version_label,
                // Resolved against the current host, not the value frozen into
                // the row when the seeder ran on Laravel Cloud — an auditor
                // following content_url was sent to the internal vanity domain.
                'content_url'   => $version->publicUrl(),
                'content_hash'  => $version->content_hash,
                'effective_at'  => $version->effective_at?->toIso8601String(),
            ];
        }
        return response()->json(['versions' => $payload]);
    }

    /**
     * Page shown by EnsureCurrentTermsAccepted when the signed-in user has
     * outstanding acceptances. Lists the documents that changed and provides
     * a single "I accept" form.
     */
    public function reviewAndAccept(Request $request): View
    {
        $user = $request->user();
        abort_unless($user, 401);

        $missing = $this->missingAcceptancesFor($user->id);

        return view('legal.review-and-accept', [
            'missing' => $missing,
        ]);
    }

    /**
     * POST: record a TermsAcceptance for every currently-required version
     * the user has not yet accepted. Re-applying an acceptance that already
     * exists is a no-op (terms_acceptances has a UNIQUE on user_id+version).
     */
    public function acceptCurrent(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $request->validate(['accept' => ['accepted']]);

        foreach ($this->missingAcceptancesFor($user->id) as $version) {
            TermsAcceptance::firstOrCreate(
                ['user_id' => $user->id, 'terms_version_id' => $version->id],
                [
                    'accepted_at' => now(),
                    'ip_address'  => $request->ip(),
                    'user_agent'  => mb_substr((string) $request->userAgent(), 0, 512),
                ],
            );
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * @return array<int, TermsVersion>
     */
    private function missingAcceptancesFor(int $userId): array
    {
        $required = $this->registry->registrationRequired();
        if ($required === []) {
            return [];
        }

        $accepted = TermsAcceptance::where('user_id', $userId)
            ->whereIn('terms_version_id', collect($required)->pluck('id')->all())
            ->pluck('terms_version_id')
            ->all();

        return array_values(array_filter(
            $required,
            fn (TermsVersion $v) => ! in_array($v->id, $accepted, true),
        ));
    }
}
