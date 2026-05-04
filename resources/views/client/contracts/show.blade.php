@extends('layouts.base')

@section('title', $contract->title . ' &middot; Vaytoven')
@section('section', 'My account')
@section('nav')
    <a href="{{ route('client.contracts.index') }}">Contracts</a>
@endsection

@section('content')
    <a href="{{ route('client.contracts.index') }}" style="color:var(--muted);font-size:13px;">← All contracts</a>

    <h1 style="margin-top:8px;">{{ $contract->title }}</h1>
    <p style="color:var(--muted);margin-top:-12px;">
        Status: <span class="pill pill-{{ $contract->status }}">{{ ucfirst($contract->status) }}</span>
    </p>

    @if ($contract->isSignable())
        <div class="card" style="background:linear-gradient(135deg,rgba(255,61,138,.04),rgba(123,44,191,.04)); border-color:rgba(214,51,132,.2);">
            <h2 style="margin-top:0;">Ready to sign</h2>
            <p>This document is ready for your review and signature. We'll redirect you to DocuSign — you'll come right back here when finished.</p>
            <a class="btn btn-primary" href="{{ route('client.contracts.sign', $contract) }}">Review &amp; sign</a>
        </div>
    @endif

    @if ($contract->status === \App\Models\Contract::STATUS_COMPLETED)
        <div class="card">
            <h2 style="margin-top:0;">Signed</h2>
            <p style="color:var(--muted);">Signed on {{ $contract->signed_at?->format('F j, Y \a\t H:i') }}.</p>
            @if ($contract->signed_pdf_path)
                <a class="btn btn-primary" href="{{ route('client.contracts.download', $contract) }}">Download signed PDF</a>
            @else
                <p style="color:var(--muted);font-size:13px;">The signed copy is being processed; refresh in a few seconds.</p>
            @endif
        </div>
    @endif

    @if ($contract->status === \App\Models\Contract::STATUS_DECLINED)
        <div class="card">
            <h2 style="margin-top:0;">Declined</h2>
            <p>You declined this contract on {{ $contract->declined_at?->format('F j, Y \a\t H:i') }}. If this was a mistake, contact Vaytoven and we can resend.</p>
        </div>
    @endif

    <div class="card">
        <h2 style="margin-top:0;">Details</h2>
        <table>
            <tr><th>Type</th><td>{{ str_replace('_', ' ', ucfirst($contract->contract_type)) }}</td></tr>
            <tr><th>Sent to</th><td>{{ $contract->client_email }}</td></tr>
            <tr><th>Sent</th><td>{{ $contract->sent_at?->format('F j, Y H:i') ?: '—' }}</td></tr>
            <tr><th>Viewed</th><td>{{ $contract->viewed_at?->format('F j, Y H:i') ?: '—' }}</td></tr>
            <tr><th>Signed</th><td>{{ $contract->signed_at?->format('F j, Y H:i') ?: '—' }}</td></tr>
        </table>
    </div>
@endsection
