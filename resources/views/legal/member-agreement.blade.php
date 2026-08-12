@extends('legal.layout')

@section('eyebrow', 'Legal · Advertising Membership Agreement')
@section('title', 'Advertising Membership Agreement')
@section('effective_date', '2026-08-12')
@section('version_label', 'v2')

@section('content')
{{-- The counsel-supplied agreement, rendered as the public reference copy.

     Two things are deliberately NOT on this page:

     - The Subscription Information block and signature lines. Those belong on
       the executed document a member signs and returns, not on a public web
       page.
     - The Credit Card Authorization form. A public HTML page must never
       collect a raw card number, expiry and CVV — Vaytoven is not PCI-DSS
       scoped for that and the data would land in a web request log. Card
       details are taken through the payment gateway, or on the signed
       paperwork, never here. --}}

<div class="legal-toc">
    <strong>Sections</strong>
    <a href="#recitals">Recitals</a>
    <a href="#subscription">1. Subscription fee and term</a>
    <a href="#services">2. Terms of services</a>
    <a href="#cancellation">3. Cancellation policy</a>
    <a href="#general">4. General provisions</a>
    <a href="#severability">5. Severability</a>
    <a href="#signatures">6. Signatures</a>
</div>

<p>
    This Subscription Membership Agreement (“Agreement”) is entered into by and between
    <strong>VAYTOVEN TECHNOLOGIES LLC</strong> (“Company”), a limited liability company with its
    principal place of business located at 500 S Australian Avenue, Suite 600, West Palm Beach,
    FL 33401, and the member identified on the executed copy of this Agreement (“Member”),
    collectively referred to as the “Parties.”
</p>

<h2 id="recitals">Recitals</h2>
<p>
    WHEREAS, VAYTOVEN TECHNOLOGIES LLC operates as a SaaS-based peer-to-peer online advertising
    platform. Vaytoven.com is a Software-as-a-Service (SaaS) digital advertising platform for
    vacation rental property owners. Our platform provides advertising, marketing, and listing
    services that allow clients to promote their vacation properties to potential travelers.
</p>
<p>
    WHEREAS, Member desires to utilize the Company's platform and services to obtain marketing
    exposure, advertising services, and potential buyer or renter inquiries for their Property;
</p>
<p>
    NOW, THEREFORE, in consideration of the mutual promises and covenants contained herein, the
    Parties agree as follows:
</p>

<h2 id="subscription">1. Subscription fee and term</h2>
<p>1.1 Member agrees to pay a one-time Subscription Fee for participation in the Company's listing and advertising program.</p>
<p>1.2 The Subscription Fee provides for one (1) active listing period of one hundred eighty (180) days.</p>
<p>1.3 If no acceptable offer is received during the initial listing term, Company will provide one (1) additional one hundred eighty (180) day renewal period at no additional cost, provided Member submits a renewal request within ten (10) days following expiration of the original listing period.</p>
<p>1.4 Member acknowledges that the Company does not guarantee the sale, rental, transfer, occupancy, value, timing, profitability, or outcome of any listing.</p>

<h2 id="services">2. Terms of services</h2>
<p>2.1 Member may list a maximum number of weeks, intervals, points, or other eligible inventory through the Company's platform, as specified on the executed copy of this Agreement.</p>
<p>2.2 Member acknowledges that Vaytoven Technologies LLC is a Software-as-a-Service Rental Listing and Advertising Program and is not a real estate broker, real estate firm, or property management company.</p>
<p>2.3 Company shall provide Member with unique login credentials granting access to the Member's listing and advertising dashboard.</p>
<p>2.4 Member represents and warrants that all information supplied to Company is accurate and complete and agrees to indemnify and hold Company harmless from any damages, losses, claims, or liabilities arising from inaccurate or incomplete information provided by Member.</p>
<p>2.5 Member authorizes the Company to share ownership, property, and listing information with affiliated companies, advertising partners, prospective renters, prospective buyers, and other third parties as reasonably necessary to perform services under this Agreement.</p>
<p>2.6 The Company shall forward rental or sale inquiries received through the platform to Member. <strong>Member remains solely responsible for negotiations, pricing, contracts, payment collection, and completion of any resulting transaction.</strong></p>
<p>2.7 Member acknowledges and agrees that their Property may be advertised through the Company's SaaS platform, digital marketing campaigns, search engine advertising, social media channels, affiliate networks, and other promotional methods at Company's discretion.</p>
<p>2.8 Member understands that the Company provides digital, software-based services delivered electronically through its platform and related technologies.</p>
<p>2.9 Company may provide Member access to its proprietary mobile application (“App”) and related software tools.</p>
<p>2.10 Services shall be deemed delivered and accessible upon activation of Member's account credentials and access to the Company's platform.</p>
<p>2.11 Member acknowledges that the Subscription Fee is paid for software access, advertising exposure, listing services, and related digital benefits provided by the Company.</p>
<p>2.12 Member agrees to comply with all terms, policies, procedures, and platform requirements established by the Company.</p>

<h2 id="cancellation">3. Cancellation policy</h2>
<p>3.1 Member may cancel this Agreement within three (3) calendar days of signing. The Member must provide written notice to the Company by emailing the request to <a href="mailto:contact@vaytoven.com">contact@vaytoven.com</a> within the cancellation period.</p>
<p>3.2 Approved refunds will be processed within up to thirty (30) calendar days.</p>

<h2 id="general">4. General provisions</h2>
<p>4.1 Once a Member is contacted regarding an inquiry or offer, the Company shall be deemed to have performed substantial services under this Agreement.</p>
<p>4.2 Member agrees to abide by all terms of this Agreement and any applicable platform guidelines.</p>
{{-- The link TEXT must not be route(), which renders an absolute URL built
     from APP_URL. This region is what LegalDocumentRegistry hashes, so an
     absolute URL makes the hash environment-specific — the agreement then has
     a different fingerprint per environment, and pointing the app at a real
     domain would force every member to re-accept an unchanged document. The
     href may be absolute; the visible text is the stable path. --}}
<p>4.3 Terms and Conditions: <a href="{{ route('legal.tos', absolute: false) }}">vaytoven.com/legal/tos</a></p>
<p>4.4 Any amendment or modification to this Agreement must be in writing and signed by both Parties.</p>
<p>4.5 This Agreement shall be governed by and construed in accordance with the laws of the State of Florida.</p>
<p>4.6 The Parties agree to attempt in good faith to resolve disputes through mediation before pursuing arbitration or litigation.</p>
<p>4.7 Electronic signatures and electronically executed copies of this Agreement shall be deemed valid and enforceable.</p>

<h2 id="severability">5. Severability</h2>
<p>If any provision of this Agreement is determined to be invalid, illegal, or unenforceable, the remaining provisions shall remain in full force and effect and shall be interpreted to best accomplish the intent of the Parties.</p>

<h2 id="signatures">6. Signatures</h2>
<p>IN WITNESS WHEREOF, the Parties have executed this Agreement as of the date first written on the executed copy.</p>
<p>
    Typing the Member's name on the executed copy constitutes a legally binding electronic
    signature. By signing this Agreement, you acknowledge and agree to our Terms of Service and
    dispute-resolution provisions, and your property will not begin to be advertised until and
    unless this Agreement is returned signed to Vaytoven.
</p>

<h2>Contact</h2>
<p>
    Vaytoven Technologies LLC<br>
    500 S Australian Ave, Suite 600<br>
    West Palm Beach, FL 33401<br>
    <a href="mailto:contact@vaytoven.com">contact@vaytoven.com</a><br>
    <a href="tel:+18777829868">(877) 782-9868</a>
</p>
@endsection
