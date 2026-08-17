{{--
  The three Member Services package cards, as links.

  Every value comes from MemberServicePackage, which is the only place a
  feature, badge, price or benefit is defined — so the homepage and the
  activation page cannot drift apart.

  Params:
    $linkTo  (string) href for each card.
    $compact (bool)   drop the feature list and description.

  An earlier version chose its element from a variable so the cards could also
  render unlinked. Blade cannot compile a closing tag whose name is an echo,
  and it took the whole page down with a parse error; the activation page
  carries its own selectable markup instead.
--}}
@php
    $compact = $compact ?? false;
@endphp

<div class="pkg-grid">
    @foreach (\App\Enums\MemberServicePackage::ordered() as $pkg)
        @php
            $weekly = $pkg->currentPricePerWeekCents();
        @endphp

        <a class="pkg-card is-{{ $pkg->value }}{{ $pkg->badge() ? ' has-badge' : '' }}"
           href="{{ $linkTo }}"
           data-track-audience="member" data-track-cta="package_{{ $pkg->value }}">

            @if ($pkg->badge())
                <span class="pkg-badge">{{ $pkg->badge() }}</span>
            @endif

            <div class="pkg-emoji" aria-hidden="true">{{ $pkg->emoji() }}</div>
            <div class="pkg-name">{{ strtoupper($pkg->label()) }}</div>
            <div class="pkg-headline">{{ $pkg->headline() }}</div>

            <div class="pkg-price">${{ number_format($weekly / 100, 0) }}<span>/week</span></div>
            <div class="pkg-allowance">{{ $pkg->propertyAllowance() }}</div>

            <p class="pkg-tagline">{{ $pkg->tagline() }}</p>

            @unless ($compact)
                <p class="pkg-desc">{{ $pkg->description() }}</p>

                <ul class="pkg-features">
                    @foreach ($pkg->features() as $feature)
                        <li class="{{ $feature['included'] ? '' : 'is-excluded' }}">
                            <span class="pkg-tick" aria-hidden="true">{{ $feature['included'] ? '✓' : '—' }}</span>
                            <span>
                                {{ $feature['label'] }}
                                @if ($feature['included'] && $feature['value'] !== '✓')
                                    <strong>{{ $feature['value'] }}</strong>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endunless

            <span class="pkg-cta">{{ $pkg->ctaLabel() }}</span>
        </a>
    @endforeach
</div>
