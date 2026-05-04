@extends('layouts.base')

@section('title', 'Contract #' . $contract->id . ' &middot; Vaytoven Admin')
@section('section', 'Admin')
@section('nav')
    <a href="{{ route('admin.contracts.index') }}">Contracts</a>
    <a href="{{ route('admin.contracts.create') }}">Send new</a>
@endsection

@section('content')
    <a href="{{ route('admin.contracts.index') }}" style="color:var(--muted);font-size:13px;">← Back to contracts</a>

    <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-top:8px;">
        <div>
            <h1 style="margin-bottom:4px;">{{ $contract->title }}</h1>
            <div style="color:var(--muted);font-size:14px;">Contract #{{ $contract->id }} &middot; <span class="pill pill-{{ $contract->status }}">{{ ucfirst($contract->status) }}</span></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            @if ($contract->signed_pdf_path)
                <a class="btn btn-primary" href="{{ route('admin.contracts.download.signed', $contract) }}">Download signed PDF</a>
            @endif
            @if ($contract->certificate_pdf_path)
                <a class="btn btn-secondary" href="{{ route('admin.contracts.download.certificate', $contract) }}">Certificate of completion</a>
            @endif
            @if (! $contract->isTerminal())
                <form method="post" action="{{ route('admin.contracts.void', $contract) }}" onsubmit="return confirm('Void this contract?');" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-danger">Void</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Client</h2>
        <table>
            <tr><th>Name</th><td>{{ $contract->client_name }}</td></tr>
            <tr><th>Email</th><td>{{ $contract->client_email }}</td></tr>
            <tr><th>Phone</th><td>{{ $contract->client_phone ?: '—' }}</td></tr>
            <tr><th>User ID</th><td>{{ $contract->user_id ?: '—' }}</td></tr>
        </table>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Contract</h2>
        <table>
            <tr><th>Type</th><td>{{ str_replace('_', ' ', ucfirst($contract->contract_type)) }}</td></tr>
            <tr><th>Source</th><td>{{ $contract->source }}</td></tr>
            <tr><th>Template ID</th><td>{{ $contract->template_id ?: '—' }}</td></tr>
            <tr><th>Envelope ID</th><td><code>{{ $contract->envelope_id ?: '—' }}</code></td></tr>
            <tr><th>Payment / invoice</th><td>{{ $contract->payment_id ?: '—' }}</td></tr>
            <tr><th>Created</th><td>{{ $contract->created_at?->format('Y-m-d H:i:s') }}</td></tr>
            <tr><th>Sent</th><td>{{ $contract->sent_at?->format('Y-m-d H:i:s') ?: '—' }}</td></tr>
            <tr><th>Viewed</th><td>{{ $contract->viewed_at?->format('Y-m-d H:i:s') ?: '—' }}</td></tr>
            <tr><th>Signed</th><td>{{ $contract->signed_at?->format('Y-m-d H:i:s') ?: '—' }}</td></tr>
            <tr><th>Completed</th><td>{{ $contract->completed_at?->format('Y-m-d H:i:s') ?: '—' }}</td></tr>
            <tr><th>Last signer IP</th><td>{{ $contract->last_signer_ip ?: '—' }}</td></tr>
            <tr><th>Last signer device</th><td style="font-family:monospace;font-size:12px;">{{ $contract->last_signer_user_agent ?: '—' }}</td></tr>
        </table>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Event history</h2>
        @if ($contract->events->isEmpty())
            <p style="color:var(--muted); margin:0;">No events recorded yet. Events arrive via the DocuSign Connect webhook.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Event</th>
                        <th>Recipient</th>
                        <th>IP</th>
                        <th>User agent</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($contract->events as $event)
                        <tr>
                            <td>{{ $event->occurred_at?->format('Y-m-d H:i:s') }}</td>
                            <td><span class="pill">{{ $event->event_type }}</span></td>
                            <td>{{ $event->recipient_email ?: '—' }}</td>
                            <td>{{ $event->ip_address ?: '—' }}</td>
                            <td style="font-family:monospace;font-size:11px;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $event->user_agent ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
