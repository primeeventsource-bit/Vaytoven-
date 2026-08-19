<?php

namespace App\Services\Chargeback;

use App\Models\Booking;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Generates the Service Usage Confirmation Certificate (FR-10.13).
 *
 * Pulls evidence via EvidenceBundleService and renders a Blade template via
 * dompdf. The output is a single-PDF, ready to attach to a chargeback rebuttal
 * across any of the 10 supported processors.
 */
class ChargebackCertificateService
{
    public function __construct(private readonly EvidenceBundleService $bundles)
    {
    }

    /**
     * Generate certificate PDF binary for a booking-scoped dispute.
     */
    public function forBooking(Booking $booking): string
    {
        $bundle = $this->bundles->generateForBooking($booking);
        return $this->renderPdf($bundle, $booking->traveler);
    }

    /**
     * Generate certificate PDF binary for a user across a date window.
     */
    public function forUser(User $user, CarbonImmutable $from, CarbonImmutable $to): string
    {
        $bundle = $this->bundles->generateForUser($user->id, $from, $to);
        return $this->renderPdf($bundle, $user);
    }

    /**
     * Generate certificate PDF binary directly from an EvidenceBundle.
     *
     * Used by per-processor DisputeAdapters that have already built the bundle
     * (typically via EvidenceBundleService::generateForDispute) and don't want
     * to re-fetch from the booking. Looks up the user by id internally.
     */
    public function forBundle(EvidenceBundle $bundle): string
    {
        $user = $bundle->user_id ? User::find($bundle->user_id) : null;
        return $this->renderPdf($bundle, $user);
    }

    /**
     * Suggested filename for a download response.
     */
    public function filenameFor(?string $confirmationCode = null, ?int $userId = null): string
    {
        $stamp = Carbon::now()->format('Ymd');
        $tail = $confirmationCode ?: "user-{$userId}";

        return "vaytoven-usage-certificate-{$tail}-{$stamp}.pdf";
    }

    private function renderPdf(EvidenceBundle $bundle, ?User $user): string
    {
        return Pdf::loadView('certificates.usage-certificate', $this->viewPayload($bundle, $user))
            ->setPaper('letter', 'portrait')
            ->output();
    }

    /**
     * Everything the certificate template is given.
     *
     * Public so it can be asserted on directly. The alternative is parsing the
     * generated PDF, which means inflating streams and decoding text runs whose
     * encoding changes with the fonts the document happens to subset — a check
     * that fails for reasons having nothing to do with the evidence. This is
     * the boundary the bug lived at: the bundle gathered the orders and the
     * template rendered them, and the payload in between quietly dropped them.
     *
     * @return array<string, mixed>
     */
    public function viewPayload(EvidenceBundle $bundle, ?User $user): array
    {
        $arr = $bundle->toArray();

        return [
            'confirmationCode' => $bundle->confirmation_code ?: null,
            'generatedAt' => $bundle->generated_at,
            'userName' => $user?->name ?? 'Unknown',
            'userEmail' => $user?->email ?? '—',
            'userSince' => $user?->created_at?->toFormattedDateString() ?? '—',
            'logins' => $arr['logins'],
            'termsAcceptances' => $arr['terms_acceptances'],
            'charges' => $arr['charges'],
            'refunds' => $arr['refunds'],
            'events' => $arr['events'],
            'consumptionCount' => $arr['consumption_events_count'],
            'passiveCount' => $arr['passive_events_count'],
            'contracts' => $arr['contracts'],

            // Vaytoven's own billing, and proof the advertising it paid for was
            // delivered.
            //
            // The bundle has always gathered these and the template has always
            // rendered them, but they were never handed across — so every
            // certificate printed "No Member Services orders for this account"
            // regardless of what the member had bought. Member Services is the
            // ONLY thing that can be charged back here, which made the document
            // an argument for the cardholder: a signed statement, on Vaytoven
            // letterhead, that no order existed.
            'member_service_orders' => $arr['member_service_orders'],
            'advertising_periods'   => $arr['advertising_periods'],
            'ad_snapshots'          => $arr['ad_snapshots'],
            'service_trail'         => $arr['service_trail'],
        ];
    }
}
