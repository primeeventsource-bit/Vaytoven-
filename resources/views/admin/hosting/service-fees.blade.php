@extends('dashboard.layout')

@section('eyebrow', 'Admin · Hosting')
@section('title', 'Service Fees')

@push('head')
    <style>
        .fee-card { background:#fff; border:1px solid var(--line); border-radius:14px; padding:20px 22px; margin-bottom:14px; }
        .fee-card h3 { margin:0 0 3px; font-size:16.5px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .fee-scope { font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase;
                     padding:2px 9px; border-radius:999px; background:#f5f1f4; color:#6b6472; }
        .fee-scope.is-default { background:#fce7f3; color:#9d174d; }
        .fee-inactive { background:#f1f5f9; color:#475569; }
        .fee-rates { display:flex; gap:26px; flex-wrap:wrap; margin:12px 0 0; font-size:13.5px; }
        .fee-rates div span { display:block; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); font-weight:650; }
        .fee-rates div strong { font-size:19px; font-variant-numeric:tabular-nums; }
        .fee-grid { display:grid; gap:0 14px; grid-template-columns:1fr; }
        @media (min-width:720px) { .fee-grid.c2 { grid-template-columns:1fr 1fr; } .fee-grid.c3 { grid-template-columns:1fr 1fr 1fr; } }
        .fee-field { margin-bottom:13px; }
        .fee-field label { display:block; font-size:11.5px; font-weight:650; letter-spacing:.06em;
                           text-transform:uppercase; color:var(--muted); margin-bottom:5px; }
        .fee-field input, .fee-field select, .fee-field textarea {
            width:100%; padding:9px 12px; border:1px solid var(--line); border-radius:8px;
            font:inherit; font-size:14px; background:var(--bg); outline:none; }
        .fee-field input:focus, .fee-field select:focus { border-color:var(--magenta); background:#fff; }
        .fee-hint { font-size:11.5px; color:var(--muted); margin-top:4px; }
        .fee-actions { display:flex; gap:14px; align-items:center; margin-top:8px; }
        .fee-actions .danger { color:#b91c1c; background:none; border:0; padding:0; cursor:pointer; font:inherit; font-weight:600; font-size:13px; }
        details.fee-new > summary { cursor:pointer; font-weight:600; color:var(--magenta); margin-bottom:14px; }
    </style>
@endpush

@section('content')
<section class="vyt-section">
    <p style="margin:0 0 8px; color:var(--muted); font-size:14px; max-width:70ch;">
        Every percentage a booking charges comes from a row here — none are hard-coded. The most
        specific configuration wins: <strong>property → host → listing source → platform default</strong>.
    </p>
    <p style="margin:0 0 22px; color:var(--muted); font-size:13.5px; max-width:70ch;">
        <strong>Split-Fee</strong> charges the host a low commission and the guest a visible service
        fee. <strong>Single-Fee</strong> puts the whole fee on the host, and the guest sees no
        separate service fee at checkout. The Split-Fee guest rate must stay between
        {{ number_format($guestMin / 100, 1) }}% and {{ number_format($guestMax / 100, 1) }}%.
    </p>

    <details class="fee-new">
        <summary>+ New fee configuration</summary>
        <form method="POST" action="{{ route('admin.hosting.service-fees.store') }}" class="fee-card">
            @csrf
            @include('admin.hosting._service-fee-fields', ['config' => null, 'structures' => $structures])
            <button type="submit" class="vyt-btn">Create configuration</button>
        </form>
    </details>

    @foreach ($configs as $config)
        <form method="POST" action="{{ route('admin.hosting.service-fees.update', $config) }}" class="fee-card">
            @csrf @method('PUT')

            <h3>
                {{ $config->name }}
                <span class="fee-scope {{ $config->scope_type === 'default' ? 'is-default' : '' }}">{{ $config->scopeLabel() }}</span>
                @unless ($config->active) <span class="fee-scope fee-inactive">Inactive</span> @endunless
            </h3>
            <div style="font-size:13px; color:var(--muted);">
                {{ $config->fee_structure->label() }} · {{ $config->fee_structure->description() }}
            </div>

            <div class="fee-rates">
                <div><span>Host pays</span>
                    <strong>{{ \App\Services\Fees\ResolvedServiceFee::formatBps($config->hostBps()) }}</strong></div>
                <div><span>Guest pays</span>
                    <strong>{{ \App\Services\Fees\ResolvedServiceFee::formatBps($config->guestBps()) }}</strong></div>
                <div><span>Effective</span>
                    <strong style="font-size:14px;">{{ $config->effective_from?->format('j M Y') ?? 'Always' }}{{ $config->effective_to ? ' → '.$config->effective_to->format('j M Y') : '' }}</strong></div>
            </div>

            <hr style="border:0; border-top:1px solid var(--line); margin:18px 0;">

            @include('admin.hosting._service-fee-fields', ['config' => $config, 'structures' => $structures])

            <div class="fee-actions">
                <button type="submit" class="vyt-btn">Save</button>
                @if ($config->scope_type !== 'default')
                    <button type="submit" class="danger"
                            formaction="{{ route('admin.hosting.service-fees.destroy', $config) }}"
                            formmethod="POST"
                            onclick="return confirm('Delete this fee configuration? Existing bookings keep their own snapshot.');">
                        Delete
                    </button>
                @endif
                <span style="font-size:12px; color:var(--muted); margin-left:auto;">
                    @if ($config->updatedBy) Last edited by {{ $config->updatedBy->name }} @endif
                </span>
            </div>
        </form>
    @endforeach

    @if ($configs->isEmpty())
        <div style="text-align:center; padding:48px; color:var(--muted); border:1px dashed var(--line); border-radius:14px;">
            No fee configurations yet. Quotes are using the built-in fallback
            (Split-Fee, host 3%, guest 15%). Create a platform default above.
        </div>
    @endif
</section>

@push('scripts')
<script>
    // Delete posts to a different action; Blade cannot emit two @method fields
    // in one form, so the DELETE verb is spoofed at submit time.
    document.querySelectorAll('button.danger[formaction]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var f = btn.form;
            if (!f.querySelector('input[name="_method"][value="DELETE"]')) {
                var m = f.querySelector('input[name="_method"]');
                if (m) { m.value = 'DELETE'; }
            }
        });
    });
</script>
@endpush
@endsection
