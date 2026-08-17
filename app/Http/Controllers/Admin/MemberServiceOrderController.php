<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MemberServiceOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\MemberServiceOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Payment status for Member Services activations.
 *
 * Read-mostly on purpose. Staff can see what was ordered, whether it paid, and
 * the NMI transaction id to quote to the processor — but the amount is not
 * editable here. Changing what an order is worth after a member has been
 * quoted it is how a $2,694 activation quietly becomes something else; if a
 * figure is genuinely wrong, cancel the order and have the member start again
 * at the right one.
 */
class MemberServiceOrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $orders = MemberServiceOrder::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->query('q'), function ($q, $term) {
                $q->where(function ($w) use ($term) {
                    $w->where('reference', 'like', "%{$term}%")
                      ->orWhere('email', 'like', "%{$term}%")
                      ->orWhere('last_name', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        // Paid totals only — an awaiting-payment order is not revenue, and
        // showing it as though it were is how a pipeline gets mistaken for a
        // bank balance.
        $paidCents = MemberServiceOrder::query()
            ->where('status', MemberServiceOrderStatus::Paid)
            ->sum('total_cents');

        return view('admin.member-services.index', [
            'orders'     => $orders,
            'statuses'   => MemberServiceOrderStatus::cases(),
            'activeStatus' => $status,
            'paidCents'  => (int) $paidCents,
            'paidCount'  => MemberServiceOrder::where('status', MemberServiceOrderStatus::Paid)->count(),
            'awaiting'   => MemberServiceOrder::where('status', MemberServiceOrderStatus::AwaitingPayment)->count(),
        ]);
    }

    /**
     * Turn a paid order into running advertising.
     *
     * Separate from payment on purpose: money arriving and a listing going
     * live are different events, often days apart, and a dispute needs both
     * timestamps rather than an assumption that one implies the other.
     */
    public function activate(
        Request $request,
        MemberServiceOrder $order,
        \App\Services\MemberServices\AdvertisingActivator $activator,
    ): \Illuminate\Http\RedirectResponse {
        $validated = $request->validate([
            'property_ids'   => ['required', 'array', 'min:1'],
            'property_ids.*' => ['integer', 'exists:properties,id'],
            'starts_at'      => ['nullable', 'date'],
        ]);

        $properties = \App\Models\Property::whereIn('id', $validated['property_ids'])->get();

        try {
            $periods = $activator->activate(
                order: $order,
                properties: $properties,
                actor: $request->user(),
                startsAt: isset($validated['starts_at'])
                    ? \Illuminate\Support\Carbon::parse($validated['starts_at'])
                    : null,
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['property_ids' => $e->getMessage()]);
        }

        \App\Services\AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'advertising.activated',
            subject:   $order,
            payload:   [
                'reference'   => $order->reference,
                'properties'  => $properties->pluck('id')->all(),
                'ends_at'     => $periods->first()?->ends_at?->toIso8601String(),
            ],
            ipAddress: $request->ip(),
        );

        return back()->with('success', sprintf(
            'Advertising activated for %d %s until %s.',
            $periods->count(),
            \Illuminate\Support\Str::plural('property', $periods->count()),
            $periods->first()?->ends_at?->format('M j, Y'),
        ));
    }

    /** Cancel an unpaid order so its link stops working. */
    public function cancel(Request $request, MemberServiceOrder $order): \Illuminate\Http\RedirectResponse
    {
        if ($order->status === MemberServiceOrderStatus::Paid) {
            return back()->withErrors(['order' => 'A paid order cannot be cancelled here — issue a refund through the processor.']);
        }

        $order->update([
            'status'      => MemberServiceOrderStatus::Cancelled,
            'staff_notes' => trim(($order->staff_notes ?? '')."\nCancelled by ".$request->user()?->email.' on '.now()->toDateTimeString()),
        ]);

        return back()->with('status', "Order {$order->reference} cancelled.");
    }
}
