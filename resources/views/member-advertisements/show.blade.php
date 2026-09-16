@extends('dashboard.layout')

@section('eyebrow', 'Your advertisement')
@section('title', $property->title)

@section('content')
    @php($ack = $acknowledgement)

    @if ($errors->has('accept'))
        <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px;">
            {{ $errors->first('accept') }}
        </div>
    @endif

    <section class="vyt-section">
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>Advertisement details</h3>
                <span class="vyt-section-meta">Reference {{ $property->reference ?? '—' }}</span>
            </div>
            <div class="vyt-card-body">
                <ul class="vyt-kv">
                    <li><span class="k">Advertisement</span><span class="v">{{ $property->title }}</span></li>
                    <li>
                        <span class="k">Live page</span>
                        <span class="v"><a href="{{ route('properties.show', $property) }}" target="_blank" rel="noopener">{{ route('properties.show', $property) }}</a></span>
                    </li>
                    <li>
                        <span class="k">Status</span>
                        <span class="v"><span class="vyt-pill">{{ ucfirst(str_replace('_', ' ', $property->status->value)) }}</span></span>
                    </li>
                    @if ($state['order'])
                        <li><span class="k">Package</span><span class="v">{{ $state['order']->package?->label() }} · {{ $state['order']->weeks }} {{ Str::plural('week', $state['order']->weeks) }}</span></li>
                    @endif
                    @if ($state['activated_at'])
                        <li><span class="k">Activated</span><span class="v">{{ et($state['activated_at'], 'M j, Y g:i A') }}</span></li>
                    @endif
                    @if ($state['period'])
                        <li><span class="k">Advertising period</span><span class="v">{{ et($state['period']->starts_at, 'M j, Y') }} – {{ et($state['period']->ends_at, 'M j, Y') }}</span></li>
                    @endif
                </ul>
            </div>
        </div>
    </section>

    <section class="vyt-section">
        <div class="vyt-card">
            <div class="vyt-card-header"><h3>Confirm your advertisement</h3></div>
            <div class="vyt-card-body" style="font-size:15px;line-height:1.6;">
                @if ($state['accepted'])
                    <p style="margin:0;">
                        <span class="vyt-pill" style="background:#ecfdf5;color:#047857;">Accepted</span>
                        You accepted this advertisement on
                        <strong>{{ et($state['accepted']->occurred_at, 'M j, Y \a\t g:i A') }}</strong>.
                    </p>
                @elseif (! $running)
                    <p style="margin:0;color:var(--muted);">
                        This advertisement is not active right now. Once it is live you can review and confirm it here.
                    </p>
                @else
                    <p style="margin:0 0 16px;">{{ $ack::TEXT }}</p>
                    <form method="POST" action="{{ route('member.advertisements.accept', $property) }}" style="margin:0;">
                        @csrf
                        <button type="submit"
                                style="background:var(--gradient);color:#fff;border:0;padding:12px 22px;border-radius:999px;font-weight:600;font-size:15px;cursor:pointer;">
                            {{ $ack::BUTTON }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </section>
@endsection
