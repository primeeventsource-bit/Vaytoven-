{{--
  The site footer, shared by every public page.

  It used to live only inside welcome.blade.php, which meant the homepage was
  the sole page with a footer AND the sole place these links could be fixed.
  Every href here is a named route — no bare strings, no anchors — so a
  renamed route breaks the build instead of quietly producing a dead link.

  The gradient the brand mark fills from is defined in an <svg><defs> that
  welcome.blade.php declares inline. Pages that include this partial without
  that block get their own local defs below, keyed to a unique id so the two
  never collide when both are present.
--}}
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    <defs>
        <linearGradient id="vyt-footer-grad" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#FF3D8A"/>
            <stop offset="50%" stop-color="#D63384"/>
            <stop offset="100%" stop-color="#7B2CBF"/>
        </linearGradient>
    </defs>
</svg>

<footer class="footer">
    <div class="footer-grid">
        <div class="footer-brand">
            <div class="brand-mark">
                <svg viewBox="0 0 64 64" width="32" height="32" aria-hidden="true">
                    <path fill="url(#vyt-footer-grad)" d="M10 8h12l10 30 10-30h12L34 56h-8z"/>
                    <circle cx="48" cy="14" r="8" fill="url(#vyt-footer-grad)"/>
                    <circle cx="48" cy="14" r="3" fill="#fff"/>
                </svg>
                <span class="brand-mark-text">Vaytoven</span>
            </div>
            <p>Curated vacation properties. Built for travelers, hosts, and property owners who want the trip to feel right.</p>
            <address>
                500 S Australian Ave, Ste 600<br>West Palm Beach, FL 33401<br>
                <a href="mailto:contact@vaytoven.com" data-track-cta="footer_email">contact@vaytoven.com</a><br>
                <a href="tel:+18777829868" data-track-cta="footer_phone">(877) 782-9868</a>
            </address>
        </div>
        <div>
            <h5>Travel</h5>
            <ul>
                <li><a href="{{ route('destinations.index') }}" data-track-audience="traveler" data-track-cta="footer_destinations">Destinations</a></li>
                <li><a href="{{ route('properties.index') }}" data-track-audience="traveler" data-track-cta="footer_all_stays">All stays</a></li>
                <li><a href="{{ route('mobile-app') }}" data-track-audience="traveler" data-track-cta="footer_mobile_app">Mobile app</a></li>
                <li><a href="{{ route('help.index') }}" data-track-audience="traveler" data-track-cta="footer_help">Help center</a></li>
                <li><a href="{{ route('trip-support.show') }}" data-track-audience="traveler" data-track-cta="footer_trip_support">Trip support</a></li>
            </ul>
        </div>
        <div>
            <h5>Earn</h5>
            <ul>
                <li><a href="{{ route('hosts.show') }}" data-track-audience="host" data-track-cta="footer_become_host">Become a host</a></li>
                <li><a href="{{ route('members.show') }}" data-track-audience="member" data-track-cta="footer_members_program">Members program</a></li>
                <li><a href="{{ route('member-services.show') }}" data-track-audience="member" data-track-cta="footer_pricing">Pricing &amp; activation</a></li>
                <li><a href="{{ route('host-resources.index') }}" data-track-audience="host" data-track-cta="footer_host_resources">Host resources</a></li>
                <li><a href="{{ route('earnings-calculator') }}" data-track-audience="host" data-track-cta="footer_earnings_calculator">Earnings calculator</a></li>
            </ul>
        </div>
        <div>
            <h5>Company</h5>
            <ul>
                <li><a href="{{ route('about') }}" data-track-audience="traveler" data-track-cta="footer_about">About</a></li>
                <li><a href="{{ route('careers.index') }}" data-track-audience="traveler" data-track-cta="footer_careers">Careers</a></li>
                <li><a href="{{ route('press.index') }}" data-track-audience="traveler" data-track-cta="footer_press">Press</a></li>
                <li><a href="{{ route('contact.show') }}" data-track-audience="traveler" data-track-cta="footer_contact">Contact</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <div>&copy; {{ date('Y') }} Vaytoven Technologies LLC. All Rights Reserved.</div>
        <div>
            <a href="{{ route('legal.privacy') }}" style="margin-right:18px;" data-track-audience="traveler" data-track-cta="footer_privacy">Privacy</a>
            <a href="{{ route('legal.tos') }}" style="margin-right:18px;" data-track-audience="traveler" data-track-cta="footer_tos">Terms</a>
            <a href="{{ route('legal.member-agreement') }}" data-track-audience="member" data-track-cta="footer_member_agreement">Member Agreement</a>
        </div>
    </div>
</footer>
