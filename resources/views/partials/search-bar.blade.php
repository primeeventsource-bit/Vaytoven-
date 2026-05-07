{{--
  Vrbo-style search bar — three fields (Where / When / Guests) + a prominent
  search CTA. Renders horizontally on desktop (≥820px), stacks vertically on
  mobile. Drop into any page and it submits to /properties with q,
  check_in, check_out, adults, children, infants query params.

  Pass $compact = true on results pages so the bar shrinks (less hero
  treatment, sits inline at top).

  Bound JS lives in /vyt-search.js — autocomplete + dual-month calendar +
  guests counter + form serialise. Loaded once even if multiple bars exist.
--}}
@php
    $compact = $compact ?? false;
    $defaults = $defaults ?? [];
@endphp

<form class="vyt-search {{ $compact ? 'is-compact' : '' }}" method="GET" action="{{ route('properties.index') }}" data-vyt-search>
    {{-- Where --}}
    <label class="vyt-search-field" data-vyt-field="destination">
        <span class="vyt-search-label">Where</span>
        <input type="text"
               name="q"
               value="{{ $defaults['q'] ?? '' }}"
               placeholder="Search destinations"
               autocomplete="off"
               data-vyt-search-input
               aria-label="Destination">
        <div class="vyt-search-suggest" data-vyt-suggest hidden></div>
    </label>

    {{-- When --}}
    <button type="button" class="vyt-search-field vyt-search-trigger" data-vyt-field="dates" data-vyt-dates-trigger>
        <span class="vyt-search-label">When</span>
        <span class="vyt-search-value" data-vyt-dates-display>
            {{ ($defaults['check_in'] ?? '') ? trim(($defaults['check_in'] ?? '').' — '.($defaults['check_out'] ?? ''), ' —') : 'Check-in — Check-out' }}
        </span>
        <input type="hidden" name="check_in"  value="{{ $defaults['check_in']  ?? '' }}" data-vyt-dates-from>
        <input type="hidden" name="check_out" value="{{ $defaults['check_out'] ?? '' }}" data-vyt-dates-to>
    </button>

    {{-- Guests --}}
    <button type="button" class="vyt-search-field vyt-search-trigger" data-vyt-field="guests" data-vyt-guests-trigger>
        <span class="vyt-search-label">Who</span>
        <span class="vyt-search-value" data-vyt-guests-display>
            @php
                $totalGuests = (int)($defaults['adults'] ?? 2) + (int)($defaults['children'] ?? 0);
            @endphp
            {{ $totalGuests }} {{ \Illuminate\Support\Str::plural('guest', $totalGuests) }}
        </span>
        <input type="hidden" name="adults"   value="{{ $defaults['adults']   ?? 2 }}" data-vyt-guests-adults>
        <input type="hidden" name="children" value="{{ $defaults['children'] ?? 0 }}" data-vyt-guests-children>
        <input type="hidden" name="infants"  value="{{ $defaults['infants']  ?? 0 }}" data-vyt-guests-infants>
    </button>

    <button type="submit" class="vyt-search-submit" data-track-audience="traveler" data-track-cta="search_submit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/>
            <path d="m21 21-4.35-4.35"/>
        </svg>
        <span>Search</span>
    </button>

    {{-- Mounted on first render of the search bar; the calendar + guests
         popovers live outside the form so they overflow correctly. --}}
    <div class="vyt-search-popover" data-vyt-popover="dates" hidden>
        <div class="vyt-cal" data-vyt-cal></div>
        <div class="vyt-search-popover-actions">
            <button type="button" class="vyt-popover-clear" data-vyt-dates-clear>Clear</button>
            <button type="button" class="vyt-popover-done"  data-vyt-popover-close>Done</button>
        </div>
    </div>

    <div class="vyt-search-popover" data-vyt-popover="guests" hidden>
        @foreach ([
            ['key' => 'adults',   'label' => 'Adults',   'sub' => 'Ages 13+',                 'min' => 1, 'max' => 16, 'default' => 2],
            ['key' => 'children', 'label' => 'Children', 'sub' => 'Ages 2–12',                'min' => 0, 'max' => 10, 'default' => 0],
            ['key' => 'infants',  'label' => 'Infants',  'sub' => 'Under 2',                  'min' => 0, 'max' => 5,  'default' => 0],
        ] as $row)
            <div class="vyt-guests-row">
                <div>
                    <strong>{{ $row['label'] }}</strong>
                    <span>{{ $row['sub'] }}</span>
                </div>
                <div class="vyt-guests-stepper">
                    <button type="button" class="vyt-guests-btn" data-vyt-guests-decrement="{{ $row['key'] }}" aria-label="Decrease {{ $row['label'] }}">−</button>
                    <span data-vyt-guests-count="{{ $row['key'] }}">{{ $defaults[$row['key']] ?? $row['default'] }}</span>
                    <button type="button" class="vyt-guests-btn" data-vyt-guests-increment="{{ $row['key'] }}" data-min="{{ $row['min'] }}" data-max="{{ $row['max'] }}" aria-label="Increase {{ $row['label'] }}">+</button>
                </div>
            </div>
        @endforeach
        <div class="vyt-search-popover-actions">
            <button type="button" class="vyt-popover-done"  data-vyt-popover-close>Done</button>
        </div>
    </div>
</form>
