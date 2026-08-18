@extends('layouts.site')

@section('title', 'About')
@section('meta_description', 'Vaytoven Technologies LLC builds listing, membership and marketing software for vacation property owners and the travelers who book them.')

@section('content')
<div class="site-shell">
    <section class="site-hero">
        <p class="eyebrow">About</p>
        <h1>Vaytoven Technologies LLC</h1>
        <p class="lede">
            We build the software that lets vacation property owners advertise what they already
            own, and lets travelers find it. That is the whole business.
        </p>
    </section>

    <section class="site-section">
        <h2>What Vaytoven is</h2>
        <p>
            Vaytoven is a technology and marketing platform. Members and hosts create listings for
            vacation properties; we give them the tools to present
            it well, keep availability current, receive inquiries and offers, and manage the
            resulting bookings. Travelers browse those listings, ask questions, make offers and
            book through the platform.
        </p>
        <p>
            The company is a software and advertising business. Members retain their own
            relationships with their clubs, developers and properties.
        </p>
    </section>

    <section class="site-section">
        <h2>What we provide</h2>
        <div class="site-grid cols-3" style="margin-top:16px;">
            <div class="site-card">
                <h3>Listing and advertising software</h3>
                <p style="margin:0;">
                    Property and resort listings with photos, amenities, location, pricing and
                    availability, published to a public marketplace.
                </p>
            </div>
            <div class="site-card">
                <h3>Membership services</h3>
                <p style="margin:0;">
                    Subscription-based access to the Managed Listing Program,
                    with a dashboard for the listings and offers attached to an account.
                </p>
            </div>
            <div class="site-card">
                <h3>Transaction infrastructure</h3>
                <p style="margin:0;">
                    Inquiry and offer tracking, booking management, payment processing through a
                    licensed gateway, and the records that go with them.
                </p>
            </div>
        </div>
    </section>

    <section class="site-section">
        <h2>What Vaytoven is not</h2>
        <p>
            We think this is worth stating plainly, because platforms in this space are often
            assumed to be something they are not:
        </p>
        <ul style="color:var(--muted); line-height:1.8; max-width:68ch; padding-left:20px;">
            <li>Vaytoven Technologies LLC is <strong>not a real estate brokerage</strong> and does not broker property sales.</li>
            <li>It is <strong>not a travel agency</strong> or tour operator.</li>
            <li>It is <strong>not a property manager</strong> and does not operate, staff or maintain the properties listed on it.</li>
            <li>It <strong>does not own</strong> the properties or club inventory that members list.</li>
        </ul>
        <p>
            Listings are created and controlled by the members and hosts who own or have the right
            to advertise them. Vaytoven provides the platform they are published on.
        </p>
    </section>

    <section class="site-section">
        <h2>Where we are</h2>
        <p style="margin-bottom:24px;">
            Vaytoven Technologies LLC<br>
            500 S Australian Ave, Ste 600<br>
            West Palm Beach, FL 33401
        </p>
        <a href="{{ route('contact.show') }}" class="site-cta"
           data-track-audience="traveler" data-track-cta="about_contact">Get in touch</a>
    </section>
</div>
@endsection
