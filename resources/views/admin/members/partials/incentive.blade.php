@php
    $I     = \App\Services\Fulfillment\MemberIncentive::class;
    $EP    = \App\Services\Fulfillment\EvidencePoint::class;
    $offer = $incentive['incentive'];
    $canEdit = auth()->user()?->hasPermission('members.edit');
@endphp

<div class="vyt-card" style="margin-bottom:18px;" id="incentive">
    <div class="vyt-card-header">
        <h3>Enrollment incentive</h3>
        <span class="vyt-pill" style="font-weight:700;">{{ $I::statusLabel($incentive['status']) }}</span>
    </div>
    <div class="vyt-card-body">
        <ul class="vyt-kv" style="margin-bottom:14px;">
            <li><span class="k">Offer</span><span class="v">{{ $offer->name }}</span></li>
            <li><span class="k">Provider</span><span class="v">{{ $offer->provider }}</span></li>
            <li>
                <span class="k">Chosen by</span>
                <span class="v">
                    @if ($incentive['locked']) Presented — fixed on the record
                    @elseif ($incentive['assigned']) Assigned to this client
                    @else Default for new clients
                    @endif
                </span>
            </li>
            <li><span class="k">Version</span><span class="v vyt-mono">{{ $incentive['presented']?->incentive_version ?? $offer->version }}</span></li>
        </ul>

        @if ($incentive['eligible'] && ! $incentive['locked'] && $canEdit)
            <form method="POST" action="{{ route('admin.members.incentive-assign', $member) }}"
                  style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:16px;padding:12px;border:1px dashed var(--line);border-radius:10px;">
                @csrf
                <label style="font-size:12px;flex:1 1 240px;">Offer to send this client
                    <select name="incentive_key" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:8px;">
                        @foreach (\App\Services\Fulfillment\IncentiveCatalog::all() as $key => $option)
                            <option value="{{ $key }}" @selected($offer->key === $key)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="vyt-save" style="padding:9px 14px;">Save offer</button>
                <span class="vyt-faint" style="font-size:12px;flex-basis:100%;">Shown to the client the next time they sign in. It can't be changed after they have seen it.</span>
                @error('incentive_key') <div style="flex-basis:100%;color:#b91c1c;font-size:12.5px;">{{ $message }}</div> @enderror
            </form>
        @endif

        <div style="display:grid;gap:18px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
            @foreach (['presented' => 'Presented', 'acknowledged' => 'Acknowledged', 'delivered' => 'Delivered'] as $key => $label)
                <div>
                    <div style="font-weight:700;margin-bottom:6px;">{{ $label }}</div>
                    @if ($incentive[$key])
                        @if ($key === 'acknowledged' && ! empty($incentive[$key]->metadata['button']))
                            <div class="vyt-faint" style="font-size:12px;margin-bottom:4px;">Button: {{ $incentive[$key]->metadata['button'] }}</div>
                        @endif
                        @include('admin.members.partials.evidence-context', ['point' => $EP::fromRecord($incentive[$key], $member)])
                    @else
                        <div class="vyt-faint" style="font-size:13px;">Not recorded.</div>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($incentive['eligible'] && ! $incentive['delivered'] && $canEdit)
            <form method="POST" action="{{ route('admin.members.incentive-delivery', $member) }}"
                  style="margin-top:18px;padding-top:14px;border-top:1px solid var(--line);display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));align-items:end;">
                @csrf
                <div style="grid-column:1/-1;font-size:13px;color:var(--muted);">
                    Record delivery only with evidence the certificate reached the member — the provider's fulfilment reference,
                    certificate number or delivery confirmation. Presenting or acknowledging is not delivery.
                </div>
                <label style="font-size:12px;">Method
                    <select name="delivery_method" required style="width:100%;padding:8px;border:1px solid var(--line);border-radius:8px;">
                        <option value="email">Email from provider</option>
                        <option value="mail">Postal mail</option>
                        <option value="provider_portal">Provider portal</option>
                        <option value="other">Other</option>
                    </select>
                </label>
                <label style="font-size:12px;">Provider reference / certificate no.
                    <input name="delivery_reference" required maxlength="160" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:8px;">
                </label>
                <label style="font-size:12px;">Note
                    <input name="note" maxlength="500" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:8px;">
                </label>
                <button type="submit" class="vyt-save" style="padding:9px 14px;">Record delivery</button>
                @error('delivery_reference') <div style="grid-column:1/-1;color:#b91c1c;font-size:12.5px;">{{ $message }}</div> @enderror
            </form>
        @endif
    </div>
</div>
