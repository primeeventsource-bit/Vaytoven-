<?php

namespace App\Services\MemberServices;

use App\Enums\MemberServiceOrderStatus;
use App\Enums\MemberServicePackage;
use App\Models\MemberServiceOrder;

/**
 * Creates an activation order and fixes its price.
 *
 * The single most important property of this class: THE TOTAL IS COMPUTED
 * HERE, SERVER-SIDE, FROM THE PACKAGE AND THE WEEK COUNT. It is never read
 * from the request.
 *
 * The browser shows a running total so the member can see what they are
 * agreeing to, but that figure is decoration. If the submitted form carried an
 * amount, anyone could accept a $2,694 quote and post $2.94 — and the charge
 * would go through, because by the time it reaches the gateway it is just a
 * number the application asked for. The only defence is never to accept the
 * number in the first place.
 *
 * The weekly rate is snapshotted onto the row for the same reason in the other
 * direction: an order agreed at $449/week stays at $449/week even if Gold is
 * repriced tomorrow.
 */
class MemberServiceOrderFactory
{
    public function create(
        MemberServicePackage $package,
        int $weeks,
        array $member,
        ?int $createdByUserId = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): MemberServiceOrder {
        $weeks = $this->clampWeeks($weeks);

        // Snapshot, then multiply. Integer cents only — never floats.
        $pricePerWeekCents = $package->currentPricePerWeekCents();
        $totalCents        = $pricePerWeekCents * $weeks;

        $ttlDays = max(1, (int) setting('member_services.link_ttl_days', MemberServiceOrder::LINK_TTL_DAYS));

        return MemberServiceOrder::create([
            'reference'            => MemberServiceOrder::generateReference(),
            'first_name'           => $member['first_name'],
            'last_name'            => $member['last_name'],
            'email'                => strtolower(trim($member['email'])),
            'phone'                => $member['phone'] ?? null,
            'package'              => $package,
            'weeks'                => $weeks,
            'price_per_week_cents' => $pricePerWeekCents,
            'total_cents'          => $totalCents,
            'currency'             => 'USD',
            'status'               => MemberServiceOrderStatus::AwaitingPayment,
            'link_expires_at'      => now()->addDays($ttlDays),
            'created_by_user_id'   => $createdByUserId,
            'submitted_ip'         => $ip,
            'user_agent'           => $userAgent ? substr($userAgent, 0, 512) : null,
        ]);
    }

    /**
     * A quote for the page's running total. Same arithmetic as create(), so
     * what the member is shown and what they are charged cannot drift.
     *
     * @return array{price_per_week_cents:int,total_cents:int,weeks:int}
     */
    public function quote(MemberServicePackage $package, int $weeks): array
    {
        $weeks = $this->clampWeeks($weeks);
        $rate  = $package->currentPricePerWeekCents();

        return [
            'weeks'                => $weeks,
            'price_per_week_cents' => $rate,
            'total_cents'          => $rate * $weeks,
        ];
    }

    /**
     * At least one week, and no more than the configured ceiling.
     *
     * The ceiling is not paranoia about attackers — the field is validated —
     * it is about a representative on the phone typing 44 instead of 4.
     */
    private function clampWeeks(int $weeks): int
    {
        $max = max(1, (int) setting('member_services.max_weeks', 52));

        return max(1, min($weeks, $max));
    }
}
