<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendContractRequest;
use App\Models\Contract;
use App\Services\DocuSign\EnvelopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin-side contract management. Routes mounted under /admin/contracts.
 *
 * Auth: assumes an `auth` and an `admin` middleware are in the route group.
 * Until Breeze + role middleware are installed, the routes will simply
 * reject unauthenticated traffic via the `auth` middleware on the group.
 */
class ContractController extends Controller
{
    public function __construct(private readonly EnvelopeService $envelopes) {}

    public function index(Request $request)
    {
        $query = Contract::query()->latest('id');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                  ->orWhere('client_email', 'like', "%{$search}%")
                  ->orWhere('envelope_id', $search)
                  ->orWhere('id', $search);
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $contracts = $query->paginate(25)->withQueryString();

        return view('admin.contracts.index', [
            'contracts' => $contracts,
            'filters'   => $request->only(['q', 'status', 'from', 'to']),
            'statuses'  => $this->statusOptions(),
        ]);
    }

    public function create()
    {
        return view('admin.contracts.create', [
            'types' => $this->typeOptions(),
        ]);
    }

    public function store(SendContractRequest $request)
    {
        $contract = Contract::create([
            'user_id'       => $request->input('user_id'),
            'client_name'   => $request->string('client_name'),
            'client_email'  => $request->string('client_email'),
            'client_phone'  => $request->input('client_phone'),
            'contract_type' => $request->string('contract_type'),
            'title'         => $request->string('title'),
            'template_id'   => $request->input('template_id'),
            'payment_id'    => $request->input('payment_id'),
            'source'        => $request->input('source', Contract::SOURCE_ADMIN),
            'status'        => Contract::STATUS_DRAFT,
        ]);

        $pdfPath = null;
        if ($request->hasFile('pdf')) {
            $stored  = $request->file('pdf')->store("contracts/{$contract->id}", 'local');
            $pdfPath = Storage::disk('local')->path($stored);
        }

        try {
            $envelopeId = $this->envelopes->send($contract, $pdfPath);
        } catch (\Throwable $e) {
            $contract->forceFill(['status' => Contract::STATUS_DRAFT])->save();
            return back()
                ->withInput()
                ->with('error', 'DocuSign send failed: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.contracts.show', $contract)
            ->with('success', "Contract sent. Envelope: {$envelopeId}");
    }

    public function show(Contract $contract)
    {
        $contract->load('events');
        return view('admin.contracts.show', compact('contract'));
    }

    public function downloadSigned(Contract $contract): StreamedResponse
    {
        abort_unless($contract->signed_pdf_path, 404, 'Signed PDF not available yet.');
        return Storage::disk('local')->download(
            $contract->signed_pdf_path,
            "vaytoven-contract-{$contract->id}-signed.pdf"
        );
    }

    public function downloadCertificate(Contract $contract): StreamedResponse
    {
        abort_unless($contract->certificate_pdf_path, 404, 'Certificate not available yet.');
        return Storage::disk('local')->download(
            $contract->certificate_pdf_path,
            "vaytoven-contract-{$contract->id}-certificate.pdf"
        );
    }

    public function void(Request $request, Contract $contract)
    {
        $reason = $request->input('reason', 'Voided by Vaytoven admin.');
        $this->envelopes->void($contract, $reason);
        return back()->with('success', 'Contract voided.');
    }

    private function statusOptions(): array
    {
        return [
            Contract::STATUS_DRAFT, Contract::STATUS_SENT, Contract::STATUS_DELIVERED,
            Contract::STATUS_VIEWED, Contract::STATUS_SIGNED, Contract::STATUS_COMPLETED,
            Contract::STATUS_DECLINED, Contract::STATUS_VOIDED, Contract::STATUS_EXPIRED,
        ];
    }

    private function typeOptions(): array
    {
        return [
            Contract::TYPE_HOST_LISTING   => 'Host Listing Agreement',
            Contract::TYPE_MEMBER_PROGRAM => 'Managed Listing Program (vacation club members)',
            Contract::TYPE_BOOKING_TERMS  => 'Booking Terms',
            Contract::TYPE_CUSTOM         => 'Custom',
        ];
    }
}
