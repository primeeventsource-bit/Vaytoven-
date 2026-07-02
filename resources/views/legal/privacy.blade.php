@extends('legal.layout')

@section('eyebrow', 'Legal · Privacy Policy')
@section('title', 'Privacy Policy')
@section('effective_date', '2026-05-06')
@section('version_label', 'v1')

@section('content')
<div class="legal-toc">
    <strong>Sections</strong>
    <a href="#what">1. What we collect</a>
    <a href="#why">2. Why we collect it</a>
    <a href="#sharing">3. Who we share it with</a>
    <a href="#retention">4. How long we keep it</a>
    <a href="#rights">5. Your rights</a>
    <a href="#cookies">6. Cookies and tracking</a>
    <a href="#contact">7. Contact</a>
</div>

<h2 id="what">1. What we collect</h2>
<p>We collect information you give us directly (name, email, phone, payment instrument details handled by our processor) and information about how you use the Service (login records, IP, device, page views, search and booking activity, support conversations).</p>
<p>We do not store full card numbers. Card data is tokenised by our payment processor.</p>

<h2 id="why">2. Why we collect it</h2>
<p>To run the Service, complete bookings, process payments and refunds, prevent fraud, defend chargebacks, communicate with you, and improve product quality. Login and consumption records also support our Service Usage Confirmation evidence (see Chargeback Policy).</p>

<h2 id="sharing">3. Who we share it with</h2>
<p>Payment processors (NMI, etc.), email and SMS providers, fraud prevention vendors, and service providers acting on our behalf under written contracts. We do not sell your personal information.</p>
<p>We may share information with law enforcement when required by law, and with the issuing bank when responding to a chargeback you have initiated.</p>

<h2 id="retention">4. How long we keep it</h2>
<p>We keep account and transaction records for at least seven years to satisfy financial recordkeeping and chargeback rebuttal windows. We keep tracking events for two years. You can request deletion of optional fields at any time.</p>

<h2 id="rights">5. Your rights</h2>
<p>Depending on your jurisdiction (including under the GDPR and CCPA), you may have rights to access, correct, port, or request deletion of your personal information. Email <a href="mailto:privacy@vaytoven.com">privacy@vaytoven.com</a> with the subject line "Data Request" and we will respond within 30 days.</p>

<h2 id="cookies">6. Cookies and tracking</h2>
<p>We use a first-party visitor cookie (<code>vyt_vid</code>) and a UTM cookie (<code>vyt_utm</code>) to attribute marketing performance and stitch sessions together for fraud prevention. These cookies do not contain personal information directly. We do not run third-party advertising trackers on the marketing site.</p>

<h2 id="contact">7. Contact</h2>
<p>Privacy questions: <a href="mailto:privacy@vaytoven.com">privacy@vaytoven.com</a>.</p>
@endsection
