@extends('dashboard.layout')

@section('eyebrow', 'Admin')
@section('title', 'Listings')

@section('content')
<div class="vyt-card-header" style="padding:0 0 18px;">
    <h3>Listings</h3>
    <a href="{{ route('admin.properties.create') }}" class="site-cta" style="padding:9px 20px;font-size:14px;">
        Create a listing
    </a>
</div>

@if (session('success'))
    <div class="site-alert">{{ session('success') }}</div>
@endif

{{-- Live advertisements that are not fit to be live.
     A member pays for a fixed advertising window and the clock runs whether or
     not the page has anything on it, so this sits above everything else on the
     screen rather than in a report somebody remembers to open. --}}
@if (! empty($brokenLive))
    @php($n = count($brokenLive))
    <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
        <strong>
            {{ $n }} live {{ Str::plural('advertisement', $n) }}
            {{ $n === 1 ? 'is' : 'are' }} missing something a visitor needs
        </strong>
        <p style="margin:6px 0 12px;font-weight:400;">
            These are public right now and the members' advertising periods are running.
        </p>

        <table class="vyt-table" style="background:#fff;border-radius:8px;">
            <thead><tr><th>Listing</th><th>Member</th><th>What is missing</th><th></th></tr></thead>
            <tbody>
                @foreach ($brokenLive as $row)
                    @php($p = $row['property'])
                    <tr>
                        <td>
                            <a href="{{ route('admin.properties.show', $p) }}">{{ $p->title ?: 'Untitled' }}</a>
                            <span class="vyt-faint">&middot; #{{ $p->id }}</span>
                        </td>
                        <td class="vyt-mono" style="font-size:12.5px;">{{ $p->host?->email ?? '—' }}</td>
                        <td style="font-weight:400;">
                            @foreach ($row['blockers'] as $blocker)
                                <div>{{ $blocker }}</div>
                            @endforeach
                        </td>
                        <td>
                            <a href="{{ route('admin.properties.show', $p) }}#photos"
                               class="site-cta" style="padding:6px 14px;font-size:13px;white-space:nowrap;">
                                Fix it
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($creds = session('owner_credentials'))
    {{-- Shown once. Not stored anywhere retrievable, and not written to the
         audit log — if it is lost, issue a new one from the user screen. --}}
    <div class="site-alert" style="background:#fffbeb;border-color:#fde68a;color:#92400e;">
        <strong>One-time password for {{ $creds['email'] }}</strong>
        <div style="font-family:'SFMono-Regular',Consolas,monospace;font-size:16px;margin:8px 0;">
            {{ $creds['password'] }}
        </div>
        @if ($creds['emailed'])
            It has been emailed to them. Copy it now if you also want to read it out — it is not shown again.
        @else
            <strong>The email could not be sent</strong>, so you must pass this on yourself. It is not shown again.
        @endif
    </div>
@endif

<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Title, city or owner email"
           style="flex:1;min-width:220px;padding:9px 12px;border:1px solid var(--line);border-radius:9px;font:inherit;">
    <select name="status" style="padding:9px 12px;border:1px solid var(--line);border-radius:9px;font:inherit;">
        <option value="">All statuses</option>
        @foreach ($statuses as $s)
            <option value="{{ $s->value }}" @selected($activeStatus === $s->value)>
                {{ ucfirst(str_replace('_', ' ', $s->value)) }}
            </option>
        @endforeach
    </select>
    <button type="submit" class="site-cta" style="padding:9px 20px;font-size:14px;">Filter</button>
</form>

@if ($properties->isEmpty())
    <div class="vyt-card-empty">No listings yet.</div>
@else
<div style="overflow-x:auto;">
<table class="vyt-table" style="min-width:820px;">
    <thead>
        <tr>
            <th>Listing</th>
            <th>Owner</th>
            <th>Location</th>
            <th style="text-align:right;">Nightly</th>
            <th>Status</th>
            <th>Created</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($properties as $property)
            <tr>
                <td>
                    {{-- The admin hub, not the public page. Staff clicking a
                         listing want to manage it; the public view is a tab there. --}}
                    <a href="{{ route('admin.properties.show', $property) }}">{{ $property->title }}</a>
                    <span class="vyt-faint" style="display:block;font-size:11.5px;font-family:ui-monospace,monospace;">{{ $property->reference }}</span>
                </td>
                <td>
                    {{ $property->host?->name ?? '—' }}
                    <span class="vyt-faint" style="display:block;font-size:12px;">{{ $property->host?->email }}</span>
                </td>
                <td class="vyt-faint">
                    {{ $property->city ?? '—' }}@if ($property->country), {{ $property->country }}@endif
                    @if ($property->latitude === null)
                        <span class="vyt-faint" style="display:block;font-size:11.5px;">not geocoded</span>
                    @endif
                </td>
                <td style="text-align:right;">${{ number_format($property->price_cents / 100, 2) }}</td>
                <td><span class="vyt-pill">{{ ucfirst(str_replace('_', ' ', $property->status->value ?? $property->status)) }}</span></td>
                <td class="vyt-faint">{{ $property->created_at?->diffForHumans() }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>

<div style="margin-top:18px;">{{ $properties->links() }}</div>
@endif
@endsection
