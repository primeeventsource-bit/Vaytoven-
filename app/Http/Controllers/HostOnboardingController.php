<?php

namespace App\Http\Controllers;

use App\Enums\PaymentProcessor;
use App\Models\HostPayoutAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Host payout enrollment (FR-5.x).
 *
 * With NMI (post-Stripe, 2026-07) there is no marketplace/Connect analog:
 * guest charges settle to the Vaytoven merchant account, and hosts are paid
 * out by the platform (ACH) rather than through gateway-hosted KYC.
 *
 *   GET  /host/onboarding   →  status page (not enrolled / pending / verified / restricted)
 *   POST /host/onboarding   →  create the payout-account record; ops verifies
 *                              identity + banking details out-of-band and flips
 *                              the row to verified from the admin console.
 *
 * The old Stripe Connect webhook sync is gone — the admin Users console is
 * now the authoritative state-update path for payout accounts.
 */
class HostOnboardingController extends Controller
{
    public function index(Request $request): View
    {
        $account = $this->accountFor($request->user()->id);

        return view('host.onboarding.index', [
            'account' => $account,
        ]);
    }

    /**
     * Enroll the host for payouts. Idempotent — re-submitting when a record
     * already exists just re-renders the status page.
     */
    public function start(Request $request): RedirectResponse
    {
        $user = $request->user();

        $existing = $this->accountFor($user->id);
        if ($existing) {
            return redirect()
                ->route('host.onboarding.index')
                ->with('host_success', 'Your payout enrollment is already on file.');
        }

        HostPayoutAccount::create([
            'host_id'             => $user->id,
            'processor'           => PaymentProcessor::Nmi->value,
            // No gateway-side account object exists for platform-managed
            // payouts; key the row by host so the unique index still holds.
            'external_account_id' => "host:{$user->id}",
            'status'              => 'pending_kyc',
            'payouts_enabled'     => false,
            'charges_enabled'     => true, // guests pay the platform account
            'last_synced_at'      => now(),
            'metadata'            => ['enrolled_via' => 'host_onboarding'],
        ]);

        return redirect()
            ->route('host.onboarding.index')
            ->with('host_success', "You're enrolled. Our payments team will contact you within 1 business day to verify your identity and collect payout banking details.");
    }

    private function accountFor(int $hostId): ?HostPayoutAccount
    {
        return HostPayoutAccount::query()
            ->where('host_id', $hostId)
            ->orderByDesc('id')
            ->first();
    }
}
