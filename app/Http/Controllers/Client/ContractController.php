<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\DocuSign\EnvelopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Client-side contracts UI. Routes mounted under /account/contracts and
 * gated by the `auth` middleware. Each client only sees contracts whose
 * user_id matches the authenticated user (or whose client_email matches,
 * to handle pre-account contracts that get linked on signup).
 */
class ContractController extends Controller
{
    public function __construct(private readonly EnvelopeService $envelopes) {}

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $contracts = Contract::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('client_email', $user->email);
            })
            ->latest('id')
            ->get();

        return view('client.contracts.index', compact('contracts'));
    }

    public function show(Request $request, Contract $contract)
    {
        $this->authorizeOwnership($request, $contract);
        $contract->load('events');
        return view('client.contracts.show', compact('contract'));
    }

    /**
     * Generate a fresh embedded-signing URL and redirect the client into it.
     * DocuSign returns the user to `return_url` after signing/declining,
     * appending an `event` query param we use to refresh the local view.
     */
    public function sign(Request $request, Contract $contract)
    {
        $this->authorizeOwnership($request, $contract);

        if (! $contract->isSignable()) {
            return redirect()
                ->route('client.contracts.show', $contract)
                ->with('error', 'This contract is not currently signable (status: ' . $contract->status . ').');
        }

        $returnUrl = route('client.contracts.show', $contract) . '?event=signed';

        try {
            $url = $this->envelopes->recipientViewUrl($contract, $returnUrl);
        } catch (\Throwable $e) {
            return redirect()
                ->route('client.contracts.show', $contract)
                ->with('error', 'Could not start signing session: ' . $e->getMessage());
        }

        return redirect()->away($url);
    }

    public function downloadSigned(Request $request, Contract $contract): StreamedResponse
    {
        $this->authorizeOwnership($request, $contract);
        abort_unless($contract->signed_pdf_path, 404, 'Signed PDF not available yet.');
        return Storage::disk('local')->download(
            $contract->signed_pdf_path,
            "vaytoven-contract-{$contract->id}-signed.pdf"
        );
    }

    private function authorizeOwnership(Request $request, Contract $contract): void
    {
        $user = $request->user();
        $owns = $user && (
            $contract->user_id === $user->id
            || strcasecmp((string) $contract->client_email, (string) $user->email) === 0
        );
        abort_unless($owns, 403);
    }
}
