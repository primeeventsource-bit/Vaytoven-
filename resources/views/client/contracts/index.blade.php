@extends('layouts.base')

@section('title', 'My contracts &middot; Vaytoven')
@section('section', 'My account')
@section('nav')
    <a href="{{ route('client.contracts.index') }}" class="active">Contracts</a>
@endsection

@section('content')
    <h1>Your contracts</h1>

    @if ($contracts->isEmpty())
        <div class="card" style="text-align:center; color:var(--muted);">
            <p style="margin:0 0 6px;">No contracts on file yet.</p>
            <p style="margin:0;font-size:13px;">When Vaytoven sends you a document to sign, it will appear here.</p>
        </div>
    @else
        <div class="card" style="padding:0;overflow:hidden;">
            <table>
                <thead>
                    <tr>
                        <th>Contract</th>
                        <th>Status</th>
                        <th>Sent</th>
                        <th>Signed</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($contracts as $c)
                        <tr>
                            <td><strong>{{ $c->title }}</strong><br><span style="color:var(--muted);font-size:13px;">{{ str_replace('_', ' ', ucfirst($c->contract_type)) }}</span></td>
                            <td><span class="pill pill-{{ $c->status }}">{{ ucfirst($c->status) }}</span></td>
                            <td>{{ $c->sent_at?->format('M j, Y') ?: '—' }}</td>
                            <td>{{ $c->signed_at?->format('M j, Y') ?: '—' }}</td>
                            <td>
                                @if ($c->isSignable())
                                    <a class="btn btn-primary" href="{{ route('client.contracts.show', $c) }}">Review &amp; sign</a>
                                @else
                                    <a href="{{ route('client.contracts.show', $c) }}">View →</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
