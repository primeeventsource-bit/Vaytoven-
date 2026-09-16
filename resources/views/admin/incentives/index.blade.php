@extends('dashboard.layout')

@section('eyebrow', 'Admin · Marketing')
@section('title', 'Incentive offers')

@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
    <style>
        .io-grid { display:grid; gap:22px; grid-template-columns:repeat(auto-fit, minmax(min(100%, 460px), 1fr)); }
        .io-card { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; display:flex; flex-direction:column; }
        .io-card.is-default { border:2px solid var(--magenta); }
        .io-head { padding:14px 18px; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; border-bottom:1px solid var(--line); }
        .io-preview { max-height:520px; overflow:auto; border-bottom:1px solid var(--line); }
        .io-preview .vy-offer { zoom:.62; }
        .io-foot { padding:12px 18px; display:flex; gap:10px; align-items:center; justify-content:space-between; flex-wrap:wrap; font-size:13px; }
    </style>
@endpush

@section('content')
    <p class="vyt-faint" style="margin:0 0 18px;max-width:75ch;">
        The offer marked <strong>Default</strong> is shown to every Managed Listing Program client the next time they sign in,
        unless a different offer is chosen on that client's profile. Once a client has seen an offer it is fixed on their record.
    </p>

    <div class="io-grid">
        @foreach ($offers as $key => $offer)
            @php($c = $counts[$key] ?? collect())
            <section class="io-card {{ $key === $defaultKey ? 'is-default' : '' }}">
                <div class="io-head">
                    <strong>{{ $offer->name }}</strong>
                    @if ($key === $defaultKey)
                        <span class="vyt-pill" style="background:var(--gradient);color:#fff;font-weight:700;">Default for new clients</span>
                    @endif
                </div>
                <div class="io-preview">
                    @include('member-incentive._artwork', ['offer' => $offer])
                </div>
                <div class="io-foot">
                    <span class="vyt-faint">
                        Assigned {{ $assigned[$key] ?? 0 }} ·
                        Presented {{ $c['incentive_presented'] ?? 0 }} ·
                        Acknowledged {{ $c['incentive_acknowledged'] ?? 0 }} ·
                        Delivered {{ $c['incentive_delivered'] ?? 0 }}
                    </span>
                    @if ($key !== $defaultKey && auth()->user()?->hasPermission('members.edit'))
                        <form method="POST" action="{{ route('admin.incentives.default') }}" style="margin:0;">
                            @csrf
                            <input type="hidden" name="incentive_key" value="{{ $key }}">
                            <button type="submit" class="vyt-save" style="padding:8px 14px;">Send this to new clients</button>
                        </form>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
@endsection
