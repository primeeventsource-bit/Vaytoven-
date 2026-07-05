<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberEnquiryRequest;
use App\Jobs\NotifyOpsOfMemberEnquiry;
use App\Mail\MemberEnquiryReceived;
use App\Models\MemberEnquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MemberEnquiryController extends Controller
{
    public function store(StoreMemberEnquiryRequest $request): JsonResponse
    {
        // Admin kill switch (Settings console) — enforced server-side so a
        // cached form can't submit past a disabled program.
        if (! setting('mlp.enquiry_form_enabled', true) || ! feature('mlp_enquiry')) {
            return response()->json([
                'ok' => false,
                'error' => 'enquiries_disabled',
                'message' => 'Member enquiries are temporarily closed. Please check back soon.',
            ], 403);
        }

        $enquiry = MemberEnquiry::create([
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'club' => $request->validated('club'),
            'property' => $request->validated('property'),
            'points' => $request->validated('points'),
            'contact_window' => $request->validated('contact_window'),
            'consented_at' => now(),
            'source_url' => substr((string) $request->headers->get('referer', ''), 0, 500) ?: null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Confirmation email to the prospect (queued — instant form return).
        try {
            Mail::to($enquiry->email)->queue(new MemberEnquiryReceived($enquiry));
        } catch (Throwable $e) {
            // Mail driver is misconfigured? Log and continue — the row is the
            // source of truth and ops still gets the Slack ping below.
            Log::warning('member enquiry: confirmation mail dispatch failed: '.$e->getMessage(), [
                'enquiry_id' => $enquiry->id,
            ]);
        }

        // Slack ping to ops so a human can pick it up.
        NotifyOpsOfMemberEnquiry::dispatch($enquiry);

        return response()->json([
            'ok' => true,
            'reference' => $enquiry->reference,
        ]);
    }
}
