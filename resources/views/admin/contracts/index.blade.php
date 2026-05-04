@extends('layouts.base')

@section('title', 'Contracts &middot; Vaytoven Admin')
@section('section', 'Admin')
@section('nav')
    <a href="{{ route('admin.contracts.index') }}" class="active">Contracts</a>
    <a href="{{ route('admin.contracts.create') }}">Send new</a>
@endsection

@section('content')
    <h1>Contracts</h1>

    <form method="get" class="toolbar">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email, envelope ID, or contract ID">
        <select name="status">
            <option value="">All statuses</option>
            @foreach ($statuses as $s)
                <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        <input type="date" name="to"   value="{{ $filters['to']   ?? '' }}">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="{{ route('admin.contracts.create') }}" class="btn btn-primary" style="margin-left:auto;">Send a contract</a>
    </form>

    <div class="card" style="padding:0;overflow:hidden;">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Sent</th>
                    <th>Signed</th>
                    <th>Source</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contracts as $c)
                    <tr>
                        <td>#{{ $c->id }}</td>
                        <td>
                            <strong>{{ $c->client_name }}</strong><br>
                            <span style="color:var(--muted);font-size:13px;">{{ $c->client_email }}</span>
                        </td>
                        <td>{{ str_replace('_', ' ', ucfirst($c->contract_type)) }}</td>
                        <td><span class="pill pill-{{ $c->status }}">{{ ucfirst($c->status) }}</span></td>
                        <td>{{ $c->sent_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>{{ $c->signed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td style="color:var(--muted);">{{ $c->source }}</td>
                        <td><a href="{{ route('admin.contracts.show', $c) }}">View →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:32px;">No contracts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:18px;">{{ $contracts->links() }}</div>
@endsection
