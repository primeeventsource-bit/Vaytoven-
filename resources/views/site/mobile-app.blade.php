@extends('layouts.site')

@section('title', 'Vaytoven on mobile')
@section('meta_description', 'Manage listings, inquiries, offers and your membership from any phone. The Vaytoven web app works on mobile today.')

@section('content')
<div class="site-shell">
    <section class="site-hero">
        <p class="eyebrow">Mobile</p>
        <h1>Vaytoven on your phone</h1>
        <p class="lede">
            Everything below works on a phone browser today — no install, no waiting for an app
            store review. Sign in and it behaves like an app.
        </p>
    </section>

    <section class="site-section">
        <h2>What you can do from a phone</h2>
        <div class="site-grid cols-2" style="margin-top:16px;">
            <div class="site-card">
                <h3>Your account</h3>
                <p style="margin:0;">
                    Sign in, update your profile and contact details, change your password, and
                    manage your membership from the account area.
                </p>
            </div>
            <div class="site-card">
                <h3>Your listings</h3>
                <p style="margin:0;">
                    Review the properties attached to your account, check their details and see how
                    they appear to travelers.
                </p>
            </div>
            <div class="site-card">
                <h3>Inquiries and offers</h3>
                <p style="margin:0;">
                    See every inquiry and offer against your listings, with the buyer, amount, time
                    submitted and expiry — and accept or decline in a tap before the 24-hour window
                    closes.
                </p>
            </div>
            <div class="site-card">
                <h3>Browsing and booking</h3>
                <p style="margin:0;">
                    Search stays by destination, filter by capacity and price, view photos, and book
                    and pay through the same secure checkout as desktop.
                </p>
            </div>
            <div class="site-card">
                <h3>Programs</h3>
                <p style="margin:0;">
                    Access Managed Listing Program details tied to
                    your membership.
                </p>
            </div>
            <div class="site-card">
                <h3>Support</h3>
                <p style="margin:0;">
                    Raise a Trip Support request or use the in-page help chat, and get a reference
                    you can quote back.
                </p>
            </div>
        </div>
    </section>

    <section class="site-section">
        <h2>Native apps</h2>
        <p>
            There is no Vaytoven app in the App Store or on Google Play yet. When there is, the
            download links will appear here and nowhere else — we would rather show nothing than a
            button that does not lead to a real listing.
        </p>
        <div style="margin-top:20px; display:flex; gap:14px; flex-wrap:wrap;">
            <a href="{{ route('register') }}" class="site-cta"
               data-track-audience="traveler" data-track-cta="mobile_create_account">Create an account</a>
            <a href="{{ route('properties.index') }}" class="site-cta"
               style="background:none; color:var(--magenta); border:1px solid var(--line);"
               data-track-audience="traveler" data-track-cta="mobile_browse">Browse stays</a>
        </div>
    </section>
</div>
@endsection
