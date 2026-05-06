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
        $arr = $bundle->toArray();

        $payload = [
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
        ];

        return Pdf::loadView('certificates.usage-certificate', $payload)
            ->setPaper('letter', 'portrait')
            ->output();
    }
}
