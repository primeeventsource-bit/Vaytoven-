<?php

namespace App\Http\Controllers;

use App\Enums\PaymentProcessor;
use App\Models\HostPayoutAccount;
use App\Services\Payments\Stripe\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Stripe Connect Express onboarding for hosts (FR-5.x).
 *
 * Flow:
 *   GET  /host/onboarding              →  status page (no account / pending / verified / restricted)
 *   POST /host/onboarding              →  create Connect account + AccountLink + redirect to Stripe
 *   GET  /host/onboarding/refresh      →  re-create AccountLink (Stripe links expire ~minutes)
 *   GET  /host/onboarding/return       →  landing after Stripe redirect; status display
 *
 * The webhook handler at WebhookHandler::handleAccountUpdated is the
 * authoritative state-update path — it syncs payouts_enabled / charges_enabled
 * and the rolled-up status. This controller's job is just to surface the
 * current state and redirect into Stripe's hosted UI when needed.
 *
 * Demo mode: if STRIPE_SECRET + STRIPE_KEY aren't set, the index page
 * surfaces a clear amber banner explaining that Connect requires live keys
 * to test, rather than failing on the first stripe.accounts.create call.
 */
class HostOnboardingController extends Controller
{
    public function __construct(private readonly StripeService $stripe)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $account = HostPayoutAccount::query()
            ->where('host_id', $user->id)
            ->where('processor', PaymentProcessor::Stripe->value)
            ->first();

        return view('host.onboarding.index', [
            'account'    => $account,
            'stripeLive' => $this->stripeConfigured(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        if (! $this->stripeConfigured()) {
            return redirect()
                ->route('host.onboarding.index')
                ->with('host_error', 'Stripe Connect requires STRIPE_SECRET + STRIPE_KEY to be configured.');
        }

        $user = $request->user();

        try {
            $account = $this->stripe->createConnectAccount($user);
            $url = $this->stripe->createAccountLink(
                account:    $account,
                returnUrl:  route('host.onboarding.return'),
                refreshUrl: route('host.onboarding.refresh'),
            );
        } catch (Throwable $e) {
            Log::error('host onboarding: stripe failure', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return redirect()
                ->route('host.onboarding.index')
                ->with('host_error', "We couldn't open the onboarding form. Try again, or contact support if this persists.");
        }

        return redirect()->away($url);
    }

    /**
     * Stripe redirects here if the AccountLink expired before the host
     * finished KYC. Issue a fresh link and redirect them right back in.
     */
    public function refresh(Request $request): RedirectResponse
    {
        $user = $request->user();
        $account = HostPayoutAccount::query()
            ->where('host_id', $user->id)
            ->where('processor', PaymentProcessor::Stripe->value)
            ->first();

        if (! $account || ! $this->stripeConfigured()) {
            return redirect()->route('host.onboarding.index');
        }

        try {
            $url = $this->stripe->createAccountLink(
                account:    $account,
                returnUrl:  route('host.onboarding.return'),
                refreshUrl: route('host.onboarding.refresh'),
            );
        } catch (Throwable $e) {
            Log::error('host onboarding: refresh link failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return redirect()
                ->route('host.onboarding.index')
                ->with('host_error', 'Something went wrong reopening the onboarding form. Please try again.');
        }

        return redirect()->away($url);
    }

    /**
     * Stripe redirects here after the host completes (or abandons) KYC.
     * The actual capability flags update via webhook, but landing here
     * tells us the user came back — we just re-render the index.
     */
    public function return(Request $request): RedirectResponse
    {
        return redirect()
            ->route('host.onboarding.index')
            ->with('host_success', "Thanks — we're processing your details. This usually clears in a minute or two.");
    }

    private function stripeConfigured(): bool
    {
        $secret = (string) (config('services.stripe.secret') ?? '');
        $key    = (string) (config('services.stripe.key') ?? '');
        return $secret !== '' && $key !== '' && ! str_starts_with($secret, 'sk_test_dummy');
    }
}
