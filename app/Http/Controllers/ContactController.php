<?php

namespace App\Http\Controllers;

use App\Enums\ContactDepartment;
use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * /contact — a real form that persists, not a mailto: link.
 *
 * Submissions land in `contact_messages` where staff holding `contact.view`
 * can work them, and the customer gets a reference they can quote back.
 */
class ContactController extends Controller
{
    public function show(): View
    {
        return view('site.contact', ['departments' => ContactDepartment::options()]);
    }

    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $message = ContactMessage::create([
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'department' => $request->validated('department'),
            'subject' => $request->validated('subject'),
            'message' => $request->validated('message'),
            'status' => ContactMessage::STATUS_NEW,
            'source_url' => substr((string) $request->headers->get('referer', ''), 0, 500) ?: null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('contact.show')
            ->with('contact_reference', $message->reference)
            ->with('contact_success', "Thanks — we've got your message and will reply by email.");
    }
}
