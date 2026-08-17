<?php

namespace App\Services\Members;

use App\Models\AdminAuditLog;
use App\Models\Contract;
use App\Models\LoginSession;
use App\Models\MemberEnquiry;
use App\Models\MemberOffer;
use App\Models\MemberServiceOrder;
use App\Models\Property;
use App\Models\SupportTicket;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Services\Analytics\ListingAnalytics;
use Illuminate\Support\Collection;

/**
 * Gathers everything known about one member into a single payload.
 *
 * The point of a 360 view is that staff stop opening ten screens to answer one
 * question. That only works if the assembly happens in one place — otherwise
 * each tab grows its own slightly different idea of "this member's offers",
 * and the totals on the overview stop matching the tabs underneath it.
 *
 * Member Services orders are matched by EMAIL, not user id. Activation is a
 * public flow that does not require an account, so the order and the user are
 * linked by the address the member typed. That is a real weakness — an order
 * placed under a different address is invisible here — and it is why matching
 * happens once, in this class, rather than being reinvented per tab.
 */
class MemberProfileAssembler
{
    public function __construct(private readonly ListingAnalytics $analytics)
    {
    }

    public function assemble(User $user): array
    {
        $properties = Property::query()
            ->where('host_id', $user->id)
            ->orderBy('title')
            ->get();

        $orders = MemberServiceOrder::query()
            ->where('email', $user->email)
            ->orderByDesc('created_at')
            ->get();

        return [
            'member'      => $user,
            'properties'  => $properties,
            'orders'      => $orders,
            'periods'     => \App\Models\AdvertisingPeriod::query()
                ->whereIn('member_service_order_id', $orders->pluck('id'))
                ->with(['property:id,title,city', 'activatedBy:id,email'])
                ->orderByDesc('starts_at')
                ->get(),
            'package'     => $this->currentPackage($orders),
            'offers'      => $this->offers($user),
            'contracts'   => Contract::query()
                ->where('user_id', $user->id)
                ->orWhere('client_email', $user->email)
                ->orderByDesc('created_at')
                ->get(),
            'documents'   => \App\Models\MemberDocument::query()
                ->where('user_id', $user->id)
                ->with('uploadedBy:id,email')
                ->orderByDesc('created_at')
                ->get(),
            // Drives whether the upload form renders at all — see
            // DocumentStorage. Uploads are refused rather than silently lost.
            'storageDurable' => \App\Support\Storage\DocumentStorage::isDurable(),
            'enquiries'   => MemberEnquiry::query()
                ->where('email', $user->email)
                ->orderByDesc('created_at')
                ->get(),
            'tickets'     => SupportTicket::query()
                ->where('opened_by_user_id', $user->id)
                ->orWhere('contact_email', $user->email)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
            'logins'      => LoginSession::query()
                ->where('user_id', $user->id)
                ->orderByDesc('occurred_at')
                ->limit(50)
                ->get(),
            'acceptances' => TermsAcceptance::query()
                ->where('user_id', $user->id)
                ->with('version')
                ->orderByDesc('accepted_at')
                ->get(),
            'timeline'    => $this->timeline($user, $orders),
        ] + $this->analytics->payload($properties);
    }

    /**
     * Offers in both directions.
     *
     * A member is the OWNER of offers travelers send them, and the BUYER of
     * offers they send elsewhere. Showing only one side is how staff conclude
     * a member has no activity when they have plenty.
     */
    private function offers(User $user): Collection
    {
        return MemberOffer::query()
            ->where('member_user_id', $user->id)
            ->orWhere('buyer_user_id', $user->id)
            ->with('property:id,title,city')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    /** The most recent PAID package, which is what the member actually has. */
    private function currentPackage(Collection $orders): ?MemberServiceOrder
    {
        return $orders->firstWhere('status', \App\Enums\MemberServiceOrderStatus::Paid);
    }

    /**
     * A merged chronological record, newest first.
     *
     * Assembled from the systems that already record events rather than a new
     * events table: audit log, logins, terms acceptances, orders and offers.
     * Nothing here is writable from the UI — every entry is a side effect of
     * something that happened, which is what makes it worth anything as
     * evidence.
     *
     * @return array<int, array{at: \Illuminate\Support\Carbon, label: string, detail: ?string, kind: string}>
     */
    private function timeline(User $user, Collection $orders): array
    {
        $events = [];

        $push = function (?object $at, string $kind, string $label, ?string $detail = null) use (&$events) {
            if ($at) {
                $events[] = ['at' => $at, 'kind' => $kind, 'label' => $label, 'detail' => $detail];
            }
        };

        $push($user->created_at, 'account', 'Account created', $user->email);
        $push($user->password_changed_at, 'account', 'Set their own password');

        foreach ($orders as $order) {
            $push($order->created_at, 'order',
                "{$order->package->label()} package selected",
                "{$order->weeks} ".\Illuminate\Support\Str::plural('week', $order->weeks)
                ." × \${$order->pricePerWeekDollars()} = \${$order->totalDollars()} · {$order->reference}");

            $push($order->paid_at, 'payment', 'Payment approved',
                "\${$order->totalDollars()}"
                .($order->nmi_transaction_id ? " · NMI #{$order->nmi_transaction_id}" : ''));
        }

        foreach (TermsAcceptance::where('user_id', $user->id)->with('version')->get() as $acceptance) {
            $push($acceptance->accepted_at, 'legal',
                'Accepted '.($acceptance->version?->kind ?? 'terms')
                .' '.($acceptance->version?->version_label ?? ''),
                $acceptance->ip_address ? 'IP '.$acceptance->ip_address : null);
        }

        foreach (Property::where('host_id', $user->id)->get() as $property) {
            $push($property->created_at, 'listing', "Listing created: {$property->title}",
                $property->city);
        }

        foreach (LoginSession::where('user_id', $user->id)
                     ->where('auth_event', 'login')
                     ->orderByDesc('occurred_at')->limit(20)->get() as $login) {
            $push($login->occurred_at, 'login', 'Signed in',
                trim(implode(' · ', array_filter([
                    $login->ip_address,
                    trim(($login->city ? $login->city.', ' : '').($login->country ?? '')) ?: null,
                    $login->browser,
                ]))) ?: null);
        }

        foreach (AdminAuditLog::where('subject_type', User::class)
                     ->where('subject_id', $user->id)
                     ->orderByDesc('occurred_at')->limit(50)->get() as $log) {
            $push($log->occurred_at, 'admin', 'Staff action: '.$log->action,
                $log->ip_address ? 'from '.$log->ip_address : null);
        }

        usort($events, fn ($a, $b) => $b['at'] <=> $a['at']);

        return array_slice($events, 0, 200);
    }
}
