@extends('dashboard.layout')

@section('eyebrow', 'Property')
@section('title', $property->title)

@push('head')
    <style>
        .vyt-prop-head { background:#fff; border:1px solid var(--line); border-radius:14px; padding:22px; margin-bottom:16px; }
        .vyt-prop-head .ref { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:13px; color:var(--muted); }
        .vyt-prop-head h2 { margin:4px 0 8px; font-size:21px; font-family:'Fraunces',serif; }
        .vyt-prop-head .meta { color:var(--muted); font-size:13.5px; display:grid; gap:2px; }
        .vyt-pill { display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; background:#f3f4f6; color:#374151; }
        .vyt-pill.is-active { background:#ecfdf5; color:#047857; }
        .vyt-pill.is-draft  { background:#fef3c7; color:#92400e; }
        .vyt-pill.is-paused { background:#fef2f2; color:#b91c1c; }

        .vyt-stats { display:grid; gap:12px; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); margin-bottom:16px; }
        .vyt-stat { background:#fff; border:1px solid var(--line); border-radius:12px; padding:16px 18px; }
        .vyt-stat .n { font-size:26px; font-weight:600; font-family:'Fraunces',serif; }
        .vyt-stat .k { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); }

        .vyt-proptabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:18px; }
        .vyt-proptabs a { padding:8px 14px; font-size:13.5px; border:1px solid var(--line); border-radius:8px; background:#fff; color:var(--ink); }
        .vyt-proptabs a:hover { border-color:var(--magenta); text-decoration:none; }
        .vyt-proptabs a.is-muted { color:var(--muted); }
    </style>
@endpush

@section('content')
    <div class="vyt-prop-head">
        <div class="ref">PROPERTY #{{ $property->reference }}</div>
        <h2>{{ $property->title }}</h2>
        <div class="meta">
            <span>Member: {{ $property->host?->name ?? '—' }}</span>
            @if ($position = $property->packagePosition())
                <span>{{ $position }}</span>
            @endif
            <span>
                Status:
                <span class="vyt-pill {{ $property->status->value === 'active' ? 'is-active' : ($property->status->value === 'paused' ? 'is-paused' : 'is-draft') }}">
                    {{ strtoupper(str_replace('_', ' ', $property->status->value)) }}
                </span>
            </span>
        </div>
    </div>

    <div class="vyt-stats">
        <div class="vyt-stat"><div class="n">{{ number_format($stats['views']) }}</div><div class="k">Views</div></div>
        <div class="vyt-stat"><div class="n">{{ number_format($stats['clicks']) }}</div><div class="k">Clicks</div></div>
        <div class="vyt-stat"><div class="n">{{ number_format($stats['saves']) }}</div><div class="k">Saves</div></div>
        <div class="vyt-stat"><div class="n">{{ number_format($stats['offers']) }}</div><div class="k">Offers</div></div>
        <div class="vyt-stat"><div class="n">{{ $property->photos->count() }}</div><div class="k">Photos</div></div>
        <div class="vyt-stat"><div class="n">{{ $property->availabilityWeeks->count() }}</div><div class="k">Weeks listed</div></div>
    </div>

    <nav class="vyt-proptabs" aria-label="Property sections">
        <a href="{{ route('admin.properties.edit', $property) }}">Edit listing</a>
        <a href="{{ route('admin.properties.edit', $property) }}#photos">Photos</a>
        <a href="{{ route('admin.properties.edit', $property) }}#availability">Availability</a>
        {{-- Filtered to this property's reference, so "Analytics" and "Offers"
             open on this listing rather than on everything. --}}
        <a href="{{ route('admin.activity.log', ['subject' => $property->reference]) }}">Activity</a>
        <a href="{{ route('admin.offers.index', ['q' => $property->reference]) }}">Offers</a>
        {{-- Works at any status: previewing a DRAFT is the point. Staff and
             the owner see it; the public still gets a 404. --}}
        <a href="{{ route('properties.show', $property) }}" target="_blank" rel="noopener">Preview as customer ↗</a>
    </nav>

    @error('delete')
        <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">{{ $message }}</div>
    @enderror

    @if (auth()->user()?->hasPermission('properties.delete'))
        {{-- Deliberately at the bottom of the actions rather than in the tab
             row. Archiving is the right answer for a listing that finished;
             this is for duplicates, test listings and drafts built against the
             wrong member. The server refuses it once the listing has been
             advertised under a paid order, because the advertising periods and
             snapshots are what prove delivery in a dispute. --}}
        <details style="margin-bottom:16px;">
            <summary style="font-size:13px;cursor:pointer;color:#b91c1c;font-weight:600;">Delete this listing</summary>
            <div class="vyt-card" style="background:#fff;border:1px solid #fecaca;border-radius:14px;padding:18px;margin-top:10px;">
                <p class="vyt-faint" style="margin:0 0 12px;font-size:13.5px;">
                    Permanent, and cannot be undone. Photos and property documents are removed from
                    storage with it. A listing that has been advertised under a paid order cannot be
                    deleted &mdash; archive it instead.
                </p>
                <form method="POST" action="{{ route('admin.properties.destroy', $property) }}" style="margin:0;">
                    @csrf @method('DELETE')
                    <input type="text" name="reason" maxlength="200" placeholder="Reason (optional)"
                           style="padding:9px 12px;border:1px solid var(--line);border-radius:8px;font-size:14px;width:55%;margin-right:10px;">
                    <button type="submit"
                            style="background:#b91c1c;color:#fff;border:0;padding:9px 18px;border-radius:999px;font-weight:600;font-size:14px;cursor:pointer;"
                            onclick="return confirm('Permanently delete {{ $property->reference }}? This cannot be undone.');">
                        Delete permanently
                    </button>
                </form>
            </div>
        </details>
    @endif

    <div class="vyt-card" style="background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;margin-bottom:16px;">
        <h3 style="margin:0 0 12px;font-size:15px;">Recent offers</h3>
        @if ($recentOffers->isEmpty())
            <p class="vyt-faint" style="margin:0;">No offers on this listing yet.</p>
        @else
            <table class="vyt-table" style="width:100%;">
                <thead><tr><th>Reference</th><th>From</th><th>Amount</th><th>Status</th><th>When</th></tr></thead>
                <tbody>
                    @foreach ($recentOffers as $offer)
                        <tr>
                            <td class="vyt-mono">{{ $offer->reference }}</td>
                            <td>{{ $offer->buyer?->name ?? 'Removed account' }}</td>
                            <td>{{ $offer->offer_amount_cents ? '$'.number_format($offer->offer_amount_cents / 100, 2) : '—' }}</td>
                            <td><span class="vyt-pill">{{ ucfirst(str_replace('_', ' ', (string) ($offer->status?->value ?? $offer->status))) }}</span></td>
                            <td class="vyt-faint">{{ et($offer->created_at, 'M j, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="vyt-card" style="background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;margin-bottom:16px;">
        <h3 style="margin:0 0 4px;font-size:15px;">Documents</h3>
        <p class="vyt-faint" style="margin:0 0 12px;font-size:13px;">
            Filed against this property. They also appear on the member's file, because
            a document about a listing is still a document about the person who owns it.
        </p>

        @if ($documents->isEmpty())
            <p class="vyt-faint" style="margin:0 0 14px;">Nothing filed against this property yet.</p>
        @else
            <table class="vyt-table" style="width:100%;">
                <thead><tr><th>Document</th><th>Type</th><th>Size</th><th>Uploaded by</th><th>When</th><th></th></tr></thead>
                <tbody>
                    @foreach ($documents as $document)
                        <tr>
                            <td>
                                {{ $document->title }}
                                <span class="vyt-faint" style="display:block;font-size:11.5px;">
                                    {{ $document->original_name }}
                                    @unless ($document->fileExists())
                                        {{-- A row whose file has gone looks available and
                                             fails on click, which is discovered at the worst
                                             possible moment. --}}
                                        <strong style="color:#b91c1c;">· file missing from storage</strong>
                                    @endunless
                                </span>
                            </td>
                            <td class="vyt-faint">{{ $document->categoryLabel() }}</td>
                            <td class="vyt-faint">{{ $document->sizeForHumans() }}</td>
                            <td class="vyt-faint">{{ $document->uploadedBy?->email ?? '—' }}</td>
                            <td class="vyt-faint">{{ et($document->created_at, 'M j, Y') }}</td>
                            <td style="white-space:nowrap;">
                                @if ($document->fileExists())
                                    <a href="{{ route('admin.properties.documents.download', [$property, $document]) }}">Download</a>
                                @endif
                                <form method="POST" action="{{ route('admin.properties.documents.destroy', [$property, $document]) }}"
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

        <div style="border-top:1px solid var(--line);padding-top:16px;margin-top:14px;">
            @if (! $storageDurable)
                <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;margin:0;">
                    <strong>Uploads are disabled on this environment.</strong>
                    There is no durable storage attached, so a document would be lost at
                    the next deploy.
                </div>
            @else
                @error('file')
                    <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">{{ $message }}</div>
                @enderror

                <form method="POST" action="{{ route('admin.properties.documents.store', $property) }}"
                      enctype="multipart/form-data">
                    @csrf
                    <div style="display:grid;gap:12px;grid-template-columns:1fr 1fr 1fr auto;align-items:end;">
                        <div class="vyt-field">
                            <label for="doc-file">File</label>
                            <input id="doc-file" type="file" name="file" required>
                        </div>
                        <div class="vyt-field">
                            <label for="doc-category">Type</label>
                            <select id="doc-category" name="category">
                                @foreach (\App\Models\MemberDocument::CATEGORIES as $value => $label)
                                    <option value="{{ $value }}" @selected($value === 'property_document')>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="vyt-field">
                            <label for="doc-title">Title <span style="text-transform:none;letter-spacing:0;">(optional)</span></label>
                            <input id="doc-title" type="text" name="title" placeholder="Defaults to the filename">
                        </div>
                        <button type="submit" class="vyt-save" style="padding:9px 20px;font-size:14px;">Upload</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
    <div class="vyt-card" style="background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;">
        <h3 style="margin:0 0 4px;font-size:15px;">Version history</h3>
        <p class="vyt-faint" style="margin:0 0 12px;font-size:13px;">
            A point-in-time copy is taken whenever a material field changes, so what
            was advertised during a member's service period can be shown exactly as
            it stood. Each is content-hashed; a snapshot that no longer matches its
            hash is flagged rather than trusted.
        </p>

        @if ($snapshots->isEmpty())
            <p class="vyt-faint" style="margin:0;">No snapshots recorded yet.</p>
        @else
            <table class="vyt-table" style="width:100%;">
                <thead><tr><th>Version</th><th>Reason</th><th>Taken</th><th>Integrity</th></tr></thead>
                <tbody>
                    @foreach ($snapshots as $i => $snapshot)
                        <tr>
                            <td>Version {{ $snapshots->count() - $i }}</td>
                            <td class="vyt-faint">{{ $snapshot->reasonLabel() }}</td>
                            <td class="vyt-faint">{{ et($snapshot->created_at, 'M j, Y g:ia') }}</td>
                            <td>
                                @if ($snapshot->isIntact())
                                    <span class="vyt-pill is-active">Intact</span>
                                @else
                                    <span class="vyt-pill is-paused">Hash mismatch</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
