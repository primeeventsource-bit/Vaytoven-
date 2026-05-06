@extends('properties.layout')

@section('title', 'Host onboarding')

@section('content')

    <div class="props-eyebrow">For hosts</div>
    <h1 class="props-title">List your property</h1>
    <p class="props-meta">Stripe-hosted identity verification — required once before payouts can start.</p>

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

    @unless ($stripeLive)
        <div style="background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:14px 18px; border-radius:12px; margin: 18px 0; font-size:13px;">
            <strong>Demo mode.</strong> Stripe Connect requires live <code style="font-family:'SFMono-Regular',Consolas,monospace; background:#fff; padding:1px 6px; border-radius:4px;">STRIPE_SECRET</code> + <code style="font-family:'SFMono-Regular',Consolas,monospace; background:#fff; padding:1px 6px; border-radius:4px;">STRIPE_KEY</code> to start the onboarding flow. The page below shows what hosts will see once those are set.
        </div>
    @endunless

    <div class="props-detail-grid">
        <div>
            @php
                $statusMap = [
                    'pending_kyc' => ['tone' => 'background:#fffbeb; color:#92400e;', 'label' => 'Pending verification', 'note' => 'Stripe is reviewing your details. Most accounts clear within minutes; some take up to a business day.'],
                    'verified'    => ['tone' => 'background:#ecfdf5; color:#047857;', 'label' => 'Verified',              'note' => 'Your account is ready. Payouts will release 24 hours after each guest check-in.'],
                    'restricted'  => ['tone' => 'background:#fef2f2; color:#b91c1c;', 'label' => 'Restricted',            'note' => 'Stripe needs more information. Click "Resume onboarding" to provide it.'],
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
                <h2>What we need from you</h2>
                <ul style="font-size:14px; line-height:1.7; padding-left: 22px;">
                    <li>Legal name and date of birth (for identity verification)</li>
                    <li>Business type — usually <em>individual</em> for a single property owner</li>
                    <li>Bank account number for payouts (Stripe verifies micro-deposits)</li>
                    <li>Government-issued photo ID — one quick scan via phone or webcam</li>
                </ul>
                <p style="font-size:13px; color:var(--muted);">All of this is collected by Stripe directly on Stripe-hosted pages. Vaytoven never sees your bank credentials or ID document.</p>
            </section>

            <section class="props-detail-section">
                <h2>What happens after verification</h2>
                <ul style="font-size:14px; line-height:1.7; padding-left: 22px;">
                    <li>Your listings can accept charges (currently 3% host fee).</li>
                    <li>Payouts release 24 hours after guest check-in.</li>
                    <li>Funds clear to your bank in 1–2 business days for most currencies.</li>
                </ul>
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
                        Continue verification
                    @endif
                </h3>

                @if (! $account || $account->status !== 'verified')
                    <p style="font-size:13px; color:var(--muted); margin: 0 0 16px;">
                        @if (! $account)
                            Click below to open the secure Stripe onboarding form. Most hosts complete it in 5–7 minutes.
                        @else
                            Stripe needs a few more details before payouts can release.
                        @endif
                    </p>

                    <form method="POST" action="{{ route('host.onboarding.start') }}">
                        @csrf
                        <button type="submit" class="props-book-cta" style="width:100%;" data-track-audience="host" data-track-cta="host_onboarding_start">
                            @if (! $account)
                                Start verification
                            @else
                                Resume onboarding
                            @endif
                        </button>
                    </form>
                @else
                    <p style="font-size:13px; color:var(--muted); margin: 0;">
                        Your account is verified. Listings can be activated from your host dashboard.
                    </p>
                @endif

                <p class="props-book-fineprint" style="margin-top:14px;">
                    Powered by Stripe Connect Express. Identity data is held by Stripe under PCI-DSS Level 1.
                </p>
            </div>
        </aside>
    </div>

    <script src="/vyt-track.js" defer></script>

@endsection
