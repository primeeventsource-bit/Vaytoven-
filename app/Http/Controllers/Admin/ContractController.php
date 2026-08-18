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
    /**
     * Said plainly rather than as a bare 404. A staff member looking for a
     * signed agreement needs to know the file is gone, not wonder whether they
     * clicked the wrong row.
     */
    private const MISSING_FILE = 'The stored PDF is missing. It was written to a disk that did not survive a deploy; '
        .'re-pull it from DocuSign.';

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
            // Deliberately still `local`. This copy exists only to hand DocuSign
            // a real filesystem path to upload from; object storage has no
            // ->path(). The record that matters is the signed PDF that comes
            // back from DocuSign, and that one goes to durable storage.
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

        // Read from the disk the row was written to, not the current default.
        // A contract signed before object storage was attached still lives on
        // the old disk, and pointing its path at a bucket that never held it
        // turns a missing file into a confusing one.
        abort_unless($contract->signedPdfExists(), 404, self::MISSING_FILE);

        return Storage::disk($contract->documentsDisk())->download(
            $contract->signed_pdf_path,
            "vaytoven-contract-{$contract->id}-signed.pdf"
        );
    }

    public function downloadCertificate(Contract $contract): StreamedResponse
    {
        abort_unless($contract->certificate_pdf_path, 404, 'Certificate not available yet.');
        abort_unless($contract->certificatePdfExists(), 404, self::MISSING_FILE);

        return Storage::disk($contract->documentsDisk())->download(
            $contract->certificate_pdf_path,
            "vaytoven-contract-{$contract->id}-certificate.pdf"
        );
    }

    /**
     * Re-download the signed PDF and certificate from DocuSign.
     *
     * DocuSign holds the authoritative copy; what Vaytoven stores is a
     * convenience copy. So a contract whose file was lost to an ephemeral disk
     * is recoverable, and this is how — otherwise the only route back is a
     * support ticket to DocuSign.
     */
    public function refetchDocuments(Contract $contract)
    {
        abort_unless($contract->envelope_id, 404, 'This contract was never sent to DocuSign.');

        try {
            $this->envelopes->pullCompletedDocuments($contract);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not fetch the documents from DocuSign: '.$e->getMessage());
        }

        return back()->with('success', 'Documents re-fetched from DocuSign.');
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
