@extends('properties.layout')

@section('title', 'Host payout enrollment')

@section('content')

    <div class="props-eyebrow">For hosts</div>
    <h1 class="props-title">List your property</h1>
    <p class="props-meta">One-time payout enrollment — required before your first payout can release.</p>

    @if (session('host_success'))
        <div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#047857; padding:14px 18px; border-radius:12px; margin: 18px 0; font-size:14px;">
            ✓ {{ session('host_success') }}
        </div>
    @endif
    @if (session('host_error'))
        <div style="background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:14px 18px; border-radius:12px; margin: 18px 0; font-size:14px;">
            {{ session('host_error') }}
        </div>
    @endif

    <div class="props-detail-grid">
        <div>
            @php
                $statusMap = [
                    'pending_kyc' => ['tone' => 'background:#fffbeb; color:#92400e;', 'label' => 'Pending verification', 'note' => 'Our payments team is reviewing your enrollment. Most hosts are verified within 1 business day.'],
                    'verified'    => ['tone' => 'background:#ecfdf5; color:#047857;', 'label' => 'Verified',              'note' => 'Your payout account is ready. Payouts release 24 hours after each guest check-in.'],
                    'restricted'  => ['tone' => 'background:#fef2f2; color:#b91c1c;', 'label' => 'Restricted',            'note' => 'We need more information before payouts can resume. Check your email or contact support.'],
                ];
                $key = $account?->status ?? null;
                $current = $statusMap[$key] ?? null;
            @endphp

            @if ($account && $current)
                <div class="props-card" style="padding:22px; margin-bottom:18px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                        <div>
                            <div class="props-card-loc" style="font-size:11px; letter-spacing:.08em; text-transform:uppercase; font-weight:600; margin-bottom:6px;">Status</div>
                            <span style="display:inline-block; padding:4px 14px; border-radius:999px; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; {{ $current['tone'] }}">{{ $current['label'] }}</span>
                        </div>
                        <div style="text-align:right;">
                            <div class="props-card-loc" style="font-size:11px; letter-spacing:.08em; text-transform:uppercase; font-weight:600; margin-bottom:6px;">Capabilities</div>
                            <div style="font-size:13px;">
                                Charges: <strong style="color: {{ $account->charges_enabled ? '#047857' : '#b91c1c' }};">{{ $account->charges_enabled ? 'enabled' : 'disabled' }}</strong>
                                · Payouts: <strong style="color: {{ $account->payouts_enabled ? '#047857' : '#b91c1c' }};">{{ $account->payouts_enabled ? 'enabled' : 'disabled' }}</strong>
                            </div>
                        </div>
                    </div>
                    <p style="font-size:14px; color:var(--muted); margin: 14px 0 0;">{{ $current['note'] }}</p>
                </div>
            @endif

            <section class="props-detail-section">
                <h2>How payouts work</h2>
                <ul style="font-size:14px; line-height:1.7; padding-left: 22px;">
                    <li>Guests pay Vaytoven at booking — card payments are processed on our NMI merchant gateway.</li>
                    <li>Your share is held until 24 hours after guest check-in, then released.</li>
                    <li>Payouts arrive by bank transfer (ACH) in 1–2 business days.</li>
                </ul>
            </section>

            <section class="props-detail-section">
                <h2>What we need from you</h2>
                <ul style="font-size:14px; line-height:1.7; padding-left: 22px;">
                    <li>Legal name and government-issued photo ID (for identity verification)</li>
                    <li>Bank account and routing number for ACH payouts</li>
                    <li>Tax details — usually a W-9 for US hosts</li>
                </ul>
                <p style="font-size:13px; color:var(--muted);">After you enroll, our payments team contacts you through a secure channel to collect these — never over plain email. Card data from guests is tokenized by NMI and never touches Vaytoven servers.</p>
            </section>
        </div>

        <aside>
            <div class="props-book-card">
                <h3 style="font-family:'Fraunces',serif; font-weight:600; margin:0 0 16px;">
                    @if (! $account)
                        Get started
                    @elseif ($account->status === 'verified')
                        You're all set
                    @else
                        Enrollment received
                    @endif
                </h3>

                @if (! $account)
                    <p style="font-size:13px; color:var(--muted); margin: 0 0 16px;">
                        Click below to enroll for payouts. Our payments team will reach out within 1 business day to verify your identity and banking details.
                    </p>

                    <form method="POST" action="{{ route('host.onboarding.start') }}">
                        @csrf
                        <button type="submit" class="props-book-cta" style="width:100%;" data-track-audience="host" data-track-cta="host_onboarding_start">
                            Enroll for payouts
                        </button>
                    </form>
                @elseif ($account->status === 'verified')
                    <p style="font-size:13px; color:var(--muted); margin: 0;">
                        Your payout account is verified. Listings can be activated from your host dashboard.
                    </p>
                @else
                    <p style="font-size:13px; color:var(--muted); margin: 0;">
                        We have your enrollment. Verification usually completes within 1 business day — we'll email you the moment payouts are enabled.
                    </p>
                @endif

                <p class="props-book-fineprint" style="margin-top:14px;">
                    Guest payments are processed by NMI under PCI-DSS Level 1. Payout banking details are stored with our payments provider, not on Vaytoven servers.
                </p>
            </div>
        </aside>
    </div>

    <script src="/vyt-track.js" defer></script>

@endsection
