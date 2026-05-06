@extends('legal.layout')

@section('eyebrow', 'Legal · Chargeback Policy')
@section('title', 'Chargeback Policy')
@section('effective_date', '2026-05-06')
@section('version_label', 'v1')

@section('content')
<h2>What we do when you initiate a chargeback</h2>
<p>If you dispute a Vaytoven charge with your card issuer instead of contacting Vaytoven Support first, we will compile a Service Usage Confirmation showing how the account interacted with the Service, and submit it to your issuing bank as evidence the charge is valid.</p>

<h2>What the Service Usage Confirmation contains</h2>
<ul>
    <li>Your login history (timestamps, IP, country, device fingerprint)</li>
    <li>Each version of these terms you have accepted, with the SHA-256 hash of the canonical text and the IP that accepted it</li>
    <li>Charges, refunds, and confirmation codes for each booking</li>
    <li>Consumption events: search, property views, booking creation, messaging, payment events</li>
    <li>Any signed agreement (DocuSign Service Agreement, where applicable)</li>
</ul>

<h2>Hash chain integrity</h2>
<p>Every tracking event we record is part of an append-only hash chain (SHA-256 of the prior event's hash plus the current event's content). This means any tampering with the historical record is detectable — and the issuing bank can verify the chain independently if challenged.</p>

<h2>Why we ask you to contact support first</h2>
<p>Most disputes are resolvable by Vaytoven Support directly: we can issue refunds within policy, escalate cancellations outside policy where appropriate, and review host-side issues with our Trust &amp; Safety team. Issuing-bank chargebacks cost everyone time and money and rarely produce a better outcome for the cardholder.</p>

<h2>How to contact support before filing a chargeback</h2>
<p>Open the support chat from any page on Vaytoven, or email <a href="mailto:support@vaytoven.com">support@vaytoven.com</a>. We respond within one business day.</p>

<h2>Acceptance of this policy</h2>
<p>By creating an account, listing a property, or booking a stay, you accept this Chargeback Policy in addition to the Terms of Service.</p>
@endsection
