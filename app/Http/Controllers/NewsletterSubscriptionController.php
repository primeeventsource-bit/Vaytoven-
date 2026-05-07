<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscriberRequest;
use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Public newsletter signup at /signup.
 *
 * Distinct from /register (which creates a Vaytoven user account). Captures
 * full_name + email + phone and persists to the subscribers table for
 * marketing email + promotional updates.
 *
 * Re-submitting the same email is idempotent — updateOrCreate refreshes the
 * supplementary fields (phone, source url, IP, user agent) without erroring.
 * Already-unsubscribed addresses re-activate.
 */
class NewsletterSubscriptionController extends Controller
{
    public function show(): View
    {
        return view('signup.show');
    }

    public function store(StoreSubscriberRequest $request): RedirectResponse
    {
        Subscriber::updateOrCreate(
            ['email' => strtolower(trim($request->validated('email')))],
            [
                'full_name'       => $request->validated('full_name'),
                'phone'           => $request->validated('phone') ?: null,
                'status'          => Subscriber::STATUS_ACTIVE,
                'unsubscribed_at' => null,
                'source_url'      => substr((string) $request->headers->get('referer', ''), 0, 500) ?: null,
                'ip'              => $request->ip(),
                'user_agent'      => $request->userAgent(),
            ],
        );

        return redirect()
            ->route('signup.show')
            ->with('subscriber_success', "Thanks — you're on the list. Watch your inbox for our next update.");
    }
}
