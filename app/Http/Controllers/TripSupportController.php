<?php

namespace App\Http\Controllers;

use App\Enums\SupportCategory;
use App\Http\Requests\StoreTripSupportRequest;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * /trip-support — raises a real ticket in the same queue the AI support agent
 * writes to, rather than a separate inbox nobody watches.
 *
 * Category drives priority: a request about a booking, a payment or a
 * cancellation is someone mid-trip or money that has already moved, so it
 * jumps the queue automatically.
 */
class TripSupportController extends Controller
{
    public function show(): View
    {
        return view('site.trip-support', ['categories' => SupportCategory::options()]);
    }

    public function store(StoreTripSupportRequest $request): RedirectResponse
    {
        $category = SupportCategory::from($request->validated('category'));
        $user = $request->user();

        $ticket = SupportTicket::create([
            'source' => SupportTicket::SOURCE_TRIP_SUPPORT,
            'opened_by_user_id' => $user?->id,
            'contact_name' => $request->validated('name'),
            'contact_email' => $request->validated('email'),
            'contact_phone' => $request->validated('phone'),
            'property_reference' => $request->validated('property_reference'),
            'category' => $category,
            'subject' => $request->validated('subject'),
            'body' => $request->validated('message'),
            'status' => 'open',
            'priority' => $category->priority(),
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('trip-support.show')
            ->with('support_reference', $ticket->reference)
            ->with('support_success', 'Your request is in. Quote the reference below if you need to follow up.');
    }
}
