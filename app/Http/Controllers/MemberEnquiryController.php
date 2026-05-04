<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberEnquiryRequest;
use App\Models\MemberEnquiry;
use Illuminate\Http\JsonResponse;

class MemberEnquiryController extends Controller
{
    public function store(StoreMemberEnquiryRequest $request): JsonResponse
    {
        MemberEnquiry::create([
            'first_name'     => $request->validated('first_name'),
            'last_name'      => $request->validated('last_name'),
            'email'          => $request->validated('email'),
            'phone'          => $request->validated('phone'),
            'club'           => $request->validated('club'),
            'property'       => $request->validated('property'),
            'points'         => $request->validated('points'),
            'contact_window' => $request->validated('contact_window'),
            'consented_at'   => now(),
            'source_url'     => substr((string) $request->headers->get('referer', ''), 0, 500) ?: null,
            'ip'             => $request->ip(),
            'user_agent'     => $request->userAgent(),
        ]);

        return response()->json(['ok' => true]);
    }
}
