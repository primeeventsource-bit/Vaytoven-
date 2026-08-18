{{--
    The time this property is advertised for.

    Its own forms, so it sits outside the builder form — a form inside a form
    is invalid HTML and browsers drop the inner one, which would make "Add
    week" quietly submit the whole listing instead.
--}}
<div class="vyt-section" id="availability">
    <h3>Availability</h3>
    <p class="hint">
        The dates a traveler can make an offer against. Without at least one week,
        this listing advertises a property and no time.
    </p>

    @error('starts_on')
        <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">{{ $message }}</div>
    @enderror
    @error('ends_on')
        <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">{{ $message }}</div>
    @enderror

    @if ($property->availabilityWeeks->isEmpty())
        <div class="vyt-card-empty" style="padding:18px 0;color:var(--muted);">
            No weeks listed yet.
        </div>
    @else
        <table class="vyt-table" style="width:100%;">
            <thead>
                <tr><th>Dates</th><th>Nights</th><th>Status</th><th>Last changed by</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($property->availabilityWeeks as $week)
                    <tr class="{{ $week->hasPassed() ? 'is-past' : '' }}">
                        <td>
                            {{ $week->label() }}
                            @if ($week->hasPassed())
                                {{-- Shown greyed rather than hidden: a member asking
                                     "what did you advertise last autumn" needs it. --}}
                                <span class="vyt-faint" style="display:block;font-size:11.5px;">Past</span>
                            @endif
                            @if ($week->notes)
                                <span class="vyt-faint" style="display:block;font-size:11.5px;">{{ $week->notes }}</span>
                            @endif
                        </td>
                        <td class="vyt-faint">{{ $week->nights() }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.properties.availability.update', [$property, $week]) }}"
                                  style="display:flex;gap:6px;align-items:center;">
                                @csrf
                                @method('PATCH')
                                <select name="status" style="padding:6px 8px;border:1px solid var(--line);border-radius:7px;font-size:13px;">
                                    @foreach (\App\Enums\AvailabilityWeekStatus::cases() as $status)
                                        <option value="{{ $status->value }}" @selected($week->status === $status)
                                                title="{{ $status->description() }}">{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" style="font-size:12.5px;color:var(--purple);font-weight:600;">Save</button>
                            </form>
                        </td>
                        <td class="vyt-faint" style="font-size:12.5px;">
                            {{ $week->updatedBy?->email ?? '—' }}
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.properties.availability.destroy', [$property, $week]) }}"
                                  onsubmit="return confirm('Remove {{ $week->label() }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="font-size:12.5px;color:#b91c1c;">Remove</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <form method="POST" action="{{ route('admin.properties.availability.store', $property) }}"
          style="margin-top:18px;border-top:1px solid var(--line);padding-top:18px;">
        @csrf
        <div class="vyt-grid cols-4">
            <div class="vyt-field">
                <label for="avail-start">Starts</label>
                <input id="avail-start" name="starts_on" type="date" value="{{ old('starts_on') }}" required>
            </div>
            <div class="vyt-field">
                <label for="avail-end">Ends</label>
                <input id="avail-end" name="ends_on" type="date" value="{{ old('ends_on') }}" required>
            </div>
            <div class="vyt-field">
                <label for="avail-status">Status</label>
                <select id="avail-status" name="status">
                    @foreach (\App\Enums\AvailabilityWeekStatus::cases() as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="vyt-field">
                <label for="avail-notes">Notes <span style="text-transform:none;letter-spacing:0;">(optional)</span></label>
                <input id="avail-notes" name="notes" type="text" value="{{ old('notes') }}">
            </div>
        </div>
        <button type="submit" class="vyt-save" style="margin-top:12px;padding:9px 20px;font-size:14px;">Add week</button>
    </form>

    <p class="site-note" style="margin-top:14px;font-size:12.5px;color:var(--muted);">
        <strong>Offer pending</strong> keeps a week advertised but stops it taking new offers.
        <strong>Unavailable</strong> is the member taking the time back.
        <strong>Closed</strong> is staff removing it from advertising.
    </p>
</div>
