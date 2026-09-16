@extends('dashboard.layout')

@section('eyebrow', 'Your thank-you')
@section('title', $incentive::NAME)

@section('content')
    @php($S = \App\Services\Fulfillment\MemberIncentive::class)
    <section class="vyt-section">
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>{{ $incentive::NAME }}</h3>
                <span class="vyt-section-meta">Provided by {{ $incentive::PROVIDER }}</span>
            </div>
            <div class="vyt-card-body" style="font-size:15px;line-height:1.6;">
                <ul class="vyt-kv">
                    <li><span class="k">Value</span><span class="v">{{ $incentive::VALUE }} in Dining Rewards</span></li>
                    <li><span class="k">Provider</span><span class="v">{{ $incentive::PROVIDER }}</span></li>
                    <li>
                        <span class="k">Status</span>
                        <span class="v">
                            @if ($state['status'] === $S::STATUS_DELIVERED)
                                <span class="vyt-pill" style="background:#ecfdf5;color:#047857;">Delivered</span>
                            @else
                                <span class="vyt-pill">Being issued</span>
                            @endif
                        </span>
                    </li>
                </ul>
                <ul style="margin:18px 0 0;padding-left:20px;">
                    @foreach ($incentive::INCLUDED as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
                <p style="margin:18px 0 0;color:var(--muted);font-size:13.5px;">
                    Your certificates are issued by {{ $incentive::PROVIDER }}, an independent third-party provider.
                    Questions? <a href="mailto:contact@vaytoven.com">contact@vaytoven.com</a> or (877) 782-9868.
                </p>
                <p style="margin:18px 0 0;">
                    <a href="{{ route('dashboard') }}" style="background:var(--gradient);color:#fff;padding:10px 18px;border-radius:999px;font-weight:600;">Continue to my dashboard</a>
                </p>
            </div>
        </div>
    </section>
@endsection
