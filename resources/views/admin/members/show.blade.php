@extends('dashboard.layout')

@section('eyebrow', 'Admin · Member 360')
@section('title', $member->name)

@section('content')

{{-- Header ------------------------------------------------------------- --}}
<div class="m360-head">
    <div>
        <h2 style="font-family:'Source Serif 4', serif;font-size:26px;margin:0;">{{ $member->name }}</h2>
        <div class="vyt-faint" style="margin-top:4px;">
            {{ $member->email }}
            @if ($member->phone) · {{ $member->phone }} @endif
            · Member #{{ $member->id }}
            · joined {{ et($member->created_at, 'M j, Y') }}
        </div>
    </div>
    <div style="text-align:right;">
        @if ($package)
            <span class="vyt-pill" style="background:var(--gradient);color:#fff;">
                {{ strtoupper($package->package->label()) }}
            </span>
        @else
            <span class="vyt-pill">No paid package</span>
        @endif
        @if ($member->deactivated_at)
            <span class="vyt-pill" style="background:#fef2f2;color:#991b1b;">Deactivated</span>
        @endif
        @if ($member->must_change_password)
            <span class="vyt-pill" style="background:#fffbeb;color:#92400e;">Password not yet set</span>
        @endif
    </div>
</div>

{{-- Headline numbers ---------------------------------------------------- --}}
<div class="vyt-tiles" style="margin-bottom:22px;">
    <div class="vyt-tile">
        <div class="vyt-tile-label">Properties</div>
        <span class="vyt-tile-value t-gradient">{{ $properties->count() }}</span>
    </div>
    <div class="vyt-tile">
        <div class="vyt-tile-label">Ad views · 30d</div>
        <span class="vyt-tile-value t-blue">{{ number_format($totalViews30d) }}</span>
    </div>
    <div class="vyt-tile">
        <div class="vyt-tile-label">Offers</div>
        <span class="vyt-tile-value t-pink">{{ $offers->count() }}</span>
    </div>
    <div class="vyt-tile">
        <div class="vyt-tile-label">Paid to date</div>
        <span class="vyt-tile-value t-emerald">
            ${{ number_format($orders->where('status', \App\Enums\MemberServiceOrderStatus::Paid)->sum('total_cents') / 100, 2) }}
        </span>
    </div>
</div>

@if (session('success')) <div class="site-alert">{{ session('success') }}</div> @endif

{{-- Tabs ---------------------------------------------------------------- --}}
<div class="m360-tabs">
    @foreach ($tabs as $key => $label)
        <a href="{{ route('admin.members.show', ['user' => $member, 'tab' => $key]) }}"
           class="{{ $activeTab === $key ? 'is-active' : '' }}">{{ $label }}</a>
    @endforeach
</div>

@include('admin.members.tabs.' . $activeTab)

@endsection

@push('head')
<style>
        /* Source Serif 4 is a variable font with an optical-size axis.
           Pinned so the browser holds one cut at every size rather than
           selecting a more display-like one as type grows. The property
           inherits, so this single declaration reaches every heading below it.

           Fraunces was here until its letterforms — the curled g, the flared
           C and V — kept reading as wavy at heading sizes. No axis removed
           that: WONK and SOFT were already at 0 and rendering with them
           pinned was pixel-identical, so the face itself had to change. */
        html { font-optical-sizing: none; }

    .m360-head {
        display:flex; justify-content:space-between; align-items:flex-start;
        gap:20px; flex-wrap:wrap; padding-bottom:20px;
    }
    .m360-tabs {
        display:flex; gap:4px; flex-wrap:wrap; margin-bottom:22px;
        border-bottom:1px solid var(--line); padding-bottom:10px;
    }
    .m360-tabs a {
        padding:8px 15px; border-radius:999px; font-size:13.5px; font-weight:500;
        color:var(--ink); border:1px solid transparent;
    }
    .m360-tabs a:hover { background:#faf7ff; text-decoration:none; }
    .m360-tabs a.is-active { background:var(--gradient); color:#fff; }

    .m360-timeline { list-style:none; margin:0; padding:0; }
    .m360-timeline li {
        display:grid; grid-template-columns:150px 14px 1fr; gap:14px;
        padding:12px 0; border-top:1px solid var(--line); align-items:start;
    }
    .m360-timeline li:first-child { border-top:0; }
    .m360-timeline .at { font-size:12.5px; color:var(--muted); }
    .m360-timeline .dot {
        width:10px; height:10px; border-radius:50%; margin-top:5px;
        background:var(--muted);
    }
    .m360-timeline .k-payment .dot,
    .m360-timeline .k-order   .dot { background:#059669; }
    .m360-timeline .k-legal   .dot { background:var(--purple); }
    .m360-timeline .k-login   .dot { background:#2563eb; }
    .m360-timeline .k-admin   .dot { background:#d97706; }
    .m360-timeline .k-listing .dot { background:var(--magenta); }
    .m360-timeline .detail { font-size:12.5px; color:var(--muted); margin-top:2px; }
</style>
@endpush
