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
                            @if ($contract->signedPdfExists())
                                <a href="{{ route('admin.contracts.download.signed', $contract) }}">Signed PDF</a>
                            @elseif ($contract->signed_pdf_path)
                                <span style="color:#b91c1c;">file missing</span>
                            @endif
                            @if ($contract->certificatePdfExists())
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
    <div class="vyt-card-header">
        <h3>Uploaded documents</h3>
        <span class="vyt-section-meta">{{ $documents->count() }} on file</span>
    </div>

    @if ($documents->isEmpty())
        <div class="vyt-card-empty">Nothing uploaded for this member yet.</div>
    @else
        <table class="vyt-table">
            <thead>
                <tr><th>Document</th><th>Type</th><th>Size</th><th>Uploaded by</th><th>When</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($documents as $document)
                    <tr>
                        <td>
                            {{ $document->title }}
                            <span class="vyt-faint" style="display:block;font-size:12px;">
                                {{ $document->original_name }}
                                @unless ($document->fileExists())
                                    {{-- A row whose file has gone is worse than no row:
                                         it looks available and fails on download, which
                                         is discovered at the worst moment. --}}
                                    <strong style="color:#b91c1c;">· file missing from storage</strong>
                                @endunless
                            </span>
                        </td>
                        <td class="vyt-faint">{{ $document->categoryLabel() }}</td>
                        <td class="vyt-faint">{{ $document->sizeForHumans() }}</td>
                        <td class="vyt-faint">{{ $document->uploadedBy?->email ?? '—' }}</td>
                        <td class="vyt-faint">{{ $document->created_at?->format('M j, Y') }}</td>
                        <td style="white-space:nowrap;">
                            @if ($document->fileExists())
                                <a href="{{ route('admin.members.documents.download', [$member, $document]) }}">Download</a>
                            @endif
                            <form method="POST" action="{{ route('admin.members.documents.destroy', [$member, $document]) }}"
                                  style="display:inline;margin-left:8px;"
                                  onsubmit="return confirm('Delete {{ $document->title }}? This cannot be undone.');">
                                @csrf @method('DELETE')
                                <button type="submit" style="font-size:12.5px;color:#b91c1c;">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="vyt-card-body" style="border-top:1px solid var(--line);">
        @if (! $storageDurable)
            {{-- Refused up front rather than accepting the file and losing it. --}}
            <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;margin:0;">
                <strong>Uploads are disabled on this environment.</strong>
                There is no durable storage attached, so an uploaded agreement would be
                lost the next time the site deploys. Attach a Cloud disk to this
                environment and set <code>FILESYSTEM_DISK</code> to it.
            </div>
        @else
            @error('file') <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">{{ $message }}</div> @enderror

            <form method="POST" action="{{ route('admin.members.documents.store', $member) }}"
                  enctype="multipart/form-data">
                @csrf
                <div class="site-row-2">
                    <div class="site-field">
                        <label for="doc-file">File</label>
                        <input id="doc-file" type="file" name="file" required>
                    </div>
                    <div class="site-field">
                        <label for="doc-category">Type</label>
                        <select id="doc-category" name="category">
                            @foreach (\App\Models\MemberDocument::CATEGORIES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="site-field">
                    <label for="doc-title">Title <span style="text-transform:none;letter-spacing:0;">(optional)</span></label>
                    <input id="doc-title" type="text" name="title" placeholder="Defaults to the filename">
                </div>
                <button type="submit" class="site-cta" style="padding:9px 20px;font-size:14px;">Upload</button>
            </form>

            <p class="site-note" style="margin-top:12px;">
                PDF, image, Office or CSV, up to 20&nbsp;MB. Every upload and download is
                recorded in the activity log with who did it.
            </p>
        @endif
    </div>
</div>
