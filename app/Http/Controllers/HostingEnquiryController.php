<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHostingEnquiryRequest;
use App\Models\HostingEnquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * "List your property" — the form that replaced host payout enrollment.
 *
 * There is nothing to enrol for: Vaytoven advertises listings and does not
 * collect rental funds or pay hosts, so there is no payout account, no KYC and
 * no banking details to gather. What the team actually needs is the property.
 */
class HostingEnquiryController extends Controller
{
    public function show(): View
    {
        return view('host.list-your-property');
    }

    public function store(StoreHostingEnquiryRequest $request): RedirectResponse
    {
        $enquiry = HostingEnquiry::create([
            'listing_kind' => $request->validated('listing_kind') ?: HostingEnquiry::KIND_PROPERTY,
            'user_id' => $request->user()?->id,
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'property_name' => $request->validated('property_name'),
            'property_type' => $request->validated('property_type'),
            'resort_name' => $request->validated('resort_name'),
            'club_or_developer' => $request->validated('club_or_developer'),
            'ownership_details' => $request->validated('ownership_details'),
            'city' => $request->validated('city'),
            'region' => $request->validated('region'),
            'country' => $request->validated('country'),
            'bedrooms' => $request->validated('bedrooms'),
            'bathrooms' => $request->validated('bathrooms'),
            'indicative_nightly_cents' => $request->indicativeNightlyCents(),
            'availability' => $request->validated('availability'),
            'message' => $request->validated('message'),
            'status' => HostingEnquiry::STATUS_NEW,
            'source_url' => substr((string) $request->headers->get('referer', ''), 0, 500) ?: null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Back to whichever URL the form was served from, so the alias and the
        // canonical path both behave.
        return back()
            ->with('hosting_reference', $enquiry->reference)
            ->with('hosting_success', sprintf(
                "Thanks — we've got your %s details. Our team will be in touch to talk through listing it.",
                $enquiry->isResort() ? 'resort' : 'property',
            ));
    }
}
