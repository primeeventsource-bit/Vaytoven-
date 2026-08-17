<div class="vyt-card">
    <div class="vyt-card-header">
        <h3>Contracts</h3>
        <span class="vyt-section-meta">{{ $contracts->count() }} on file</span>
    </div>

    @if ($contracts->isEmpty())
        <div class="vyt-card-empty">No contracts for this member.</div>
    @else
        <table class="vyt-table">
            <thead>
                <tr><th>Title</th><th>Type</th><th>Status</th><th>Sent</th><th>Signed</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($contracts as $contract)
                    <tr>
                        <td>{{ $contract->title }}</td>
                        <td class="vyt-faint">{{ $contract->contract_type }}</td>
                        <td><span class="vyt-pill">{{ ucfirst(str_replace('_', ' ', $contract->status)) }}</span></td>
                        <td class="vyt-faint">{{ $contract->sent_at?->format('M j, Y') ?? '—' }}</td>
                        <td class="vyt-faint">{{ $contract->completed_at?->format('M j, Y') ?? '—' }}</td>
                        <td>
                            @if ($contract->status === 'completed')
                                <a href="{{ route('admin.contracts.download.signed', $contract) }}">Signed PDF</a>
                                ·
                                <a href="{{ route('admin.contracts.download.certificate', $contract) }}">Certificate</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="vyt-card" style="margin-top:18px;">
    <div class="vyt-card-header"><h3>Legal acceptances</h3></div>
    @if ($acceptances->isEmpty())
        <div class="vyt-card-empty">This member has not accepted any terms version.</div>
    @else
        <table class="vyt-table">
            <thead><tr><th>Document</th><th>Version</th><th>Accepted</th><th>IP</th></tr></thead>
            <tbody>
                @foreach ($acceptances as $acceptance)
                    <tr>
                        <td>{{ ucfirst(str_replace('_', ' ', $acceptance->version?->kind ?? '—')) }}</td>
                        <td class="vyt-mono">
                            {{ $acceptance->version?->version_label ?? '—' }}
                            <span class="vyt-faint" style="display:block;font-size:11px;">
                                {{ Str::limit($acceptance->version?->content_hash, 12, '') }}
                            </span>
                        </td>
                        <td class="vyt-faint">{{ $acceptance->accepted_at?->format('M j, Y g:ia') }}</td>
                        <td class="vyt-mono" style="font-size:12px;">{{ $acceptance->ip_address ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="vyt-card" style="margin-top:18px;">
    <div class="vyt-card-header"><h3>Evidence</h3></div>
    <div class="vyt-card-body">
        <p style="margin:0 0 14px;">
            A signed evidence certificate covering this member's logins, terms
            acceptances and contracts.
        </p>
        <a href="{{ route('admin.users.certificate', $member) }}" class="site-cta"
           style="padding:9px 20px;font-size:14px;">Download evidence certificate (PDF)</a>

        <p class="site-note" style="margin-top:14px;">
            <strong>This certificate does not yet include Member Services payments.</strong>
            It was built for the retired booking product and reads the bookings and
            charges tables, so it shows logins, acceptances and contracts but not the
            NMI transaction on the Payments tab. Wiring orders into it is the next
            piece of the evidence work.
        </p>
    </div>
</div>

<div class="vyt-card" style="margin-top:18px;">
    <div class="vyt-card-header"><h3>Uploaded documents</h3></div>
    <div class="vyt-card-body">
        <p class="vyt-faint" style="margin:0;">
            Document upload is not built yet. Contracts above come from DocuSign;
            there is currently no way to attach an invoice, receipt or supporting
            file to a member. That is a separate slice — nothing here is silently
            dropping uploads.
        </p>
    </div>
</div>
