<?php

namespace App\Http\Controllers;

use App\Enums\MemberServicePackage;
use App\Http\Requests\StoreMemberServiceOrderRequest;
use App\Mail\MemberServicePaymentLink;
use App\Services\MemberServices\MemberServiceOrderFactory;
use App\Support\Mail\MailDeliverability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * Member Services activation — the customer-facing enrolment.
 *
 * The member completes this themselves on their own device. Staff may talk
 * them through it on the phone, but staff do not fill it in and never handle
 * the card: every transaction is a customer-initiated e-commerce sale, which
 * is what the merchant account is underwritten for.
 */
class MemberServicesController extends Controller
{
    public function __construct(private readonly MemberServiceOrderFactory $orders)
    {
    }

    public function show(): View
    {
        return view('member-services.activate', [
            'packages' => collect(MemberServicePackage::ordered())->map(fn (MemberServicePackage $p) => [
                'value'          => $p->value,
                'label'          => $p->label(),
                'cents_per_week' => $p->currentPricePerWeekCents(),
            ])->all(),
            'maxWeeks' => max(1, (int) setting('member_services.max_weeks', 52)),
        ]);
    }

    public function store(StoreMemberServiceOrderRequest $request): RedirectResponse
    {
        $package = MemberServicePackage::from($request->validated('package'));

        $order = $this->orders->create(
            package: $package,
            weeks: (int) $request->validated('weeks'),
            member: $request->safe()->only(['first_name', 'last_name', 'email', 'phone']),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        // The link is emailed as a record and so the member can come back to
        // it later — but they are sent straight on to the payment page rather
        // than being told to go and check their inbox. They are already here,
        // on their device, having just filled the form; bouncing them out to
        // email is friction that loses payments. It also means the flow works
        // when mail delivery is down, which it currently is.
        $this->emailPaymentLink($order);

        return redirect()
            ->route('member-payment.show', $order->reference)
            ->with('order_created', true);
    }

    private function emailPaymentLink(\App\Models\MemberServiceOrder $order): void
    {
        if (! MailDeliverability::isDeliverable()) {
            Log::warning('Member services payment link not emailed — mail is not deliverable.', [
                'reference' => $order->reference,
                'reason'    => MailDeliverability::reason(),
            ]);

            return;
        }

        try {
            Mail::to($order->email)->send(new MemberServicePaymentLink($order));
        } catch (Throwable $e) {
            // A failed email must not lose the order. The member is being sent
            // to the payment page regardless.
            Log::error('Member services payment link email failed.', [
                'reference' => $order->reference,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
