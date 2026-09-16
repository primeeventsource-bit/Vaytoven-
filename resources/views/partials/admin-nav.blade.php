{{--
    Staff navigation: primary sections with dropdowns.

    The flat row of tabs ran off the screen as screens were added. Every
    section and item comes from App\Support\AdminNavigation, which links only
    EXISTING routes and gates each item on the same permission its route
    enforces — nobody is shown an item that 403s.

    Desktop: hover or click a section to open it; a menu near the right edge
    opens inward. Below 1200px: one "Admin menu" button, sections expand in place.
    Built on <details> so the menus work without JavaScript; the script only
    adds hover, one-open-at-a-time, edge alignment and Escape.

    Renders nothing for a host or member, who share this layout.
--}}
@php($adminSections = \App\Support\AdminNavigation::for(auth()->user(), request()))

@if ($adminSections !== [])
    <style>
        .vyt-anav { background:#fff; border-bottom:1px solid var(--line); position:relative; z-index:40; }
        .vyt-anav-inner { max-width:1180px; margin:0 auto; padding:0 24px; display:flex; align-items:center; gap:2px; }
        .vyt-anav a, .vyt-anav summary { color:var(--muted); font-size:14px; font-weight:500; text-decoration:none; }
        .vyt-anav-top { padding:13px 12px; border-bottom:2px solid transparent; white-space:nowrap; display:inline-block; }
        .vyt-anav-top.is-current, .vyt-anav-sec.is-current > summary { color:var(--ink); border-bottom-color:var(--magenta); font-weight:600; }
        .vyt-anav-sec { position:relative; }
        .vyt-anav-sec > summary { list-style:none; cursor:pointer; padding:13px 12px; border-bottom:2px solid transparent; white-space:nowrap; user-select:none; }
        .vyt-anav-sec > summary::-webkit-details-marker { display:none; }
        .vyt-anav-sec > summary::after { content:'▾'; font-size:11px; margin-left:5px; opacity:.7; }
        .vyt-anav-sec > summary:hover, .vyt-anav a:hover { color:var(--ink); }
        .vyt-anav-menu { position:absolute; top:100%; left:0; min-width:250px; max-width:min(340px, calc(100vw - 24px));
            background:#fff; border:1px solid var(--line); border-radius:12px; box-shadow:0 12px 32px rgba(26,20,38,.12); padding:6px; }
        .vyt-anav-menu.align-right { left:auto; right:0; }
        .vyt-anav-menu a { display:block; padding:9px 12px; border-radius:8px; color:var(--ink); white-space:normal; }
        .vyt-anav-menu a:hover { background:#faf5ff; }
        .vyt-anav-menu a.is-current { background:#fdf2f8; color:var(--magenta); font-weight:600; }
        .vyt-anav-menu a.is-current::before { content:'→ '; }
        .vyt-anav-cta { margin-left:auto; color:var(--purple) !important; font-weight:600 !important; padding:13px 12px; white-space:nowrap; }
        .vyt-anav-burger { display:none; }

        @media (hover:hover) and (min-width:1200px) {
            .vyt-anav-sec:hover > .vyt-anav-menu { display:block; }
        }
        .vyt-anav-sec:not([open]) > .vyt-anav-menu { display:none; }
        @media (hover:hover) and (min-width:1200px) {
            .vyt-anav-sec:not([open]):hover > .vyt-anav-menu { display:block; }
        }

        @media (max-width:1199px) {
            .vyt-anav-inner { flex-direction:column; align-items:stretch; padding:0 16px; gap:0; }
            .vyt-anav-burger { display:flex; align-items:center; gap:8px; width:100%; background:none; border:0; padding:13px 0;
                font:inherit; font-weight:700; letter-spacing:.06em; color:var(--ink); cursor:pointer; }
            .vyt-anav-panel { display:none; padding-bottom:10px; }
            .vyt-anav.is-open .vyt-anav-panel { display:block; }
            .vyt-anav-top, .vyt-anav-sec > summary, .vyt-anav-cta { display:block; padding:12px 4px; border-bottom:1px solid var(--line); margin:0; }
            .vyt-anav-top.is-current, .vyt-anav-sec.is-current > summary { border-bottom-color:var(--line); border-left:3px solid var(--magenta); padding-left:10px; }
            .vyt-anav-sec > summary::after { content:'›'; float:right; display:inline-block; font-size:18px; line-height:1; transition:transform .15s; }
            .vyt-anav-sec[open] > summary::after { transform:rotate(90deg); }
            .vyt-anav-menu { position:static; box-shadow:none; border:0; border-radius:0; padding:4px 0 8px 12px; max-width:none; min-width:0; }
        }
        @media (min-width:1200px) {
            .vyt-anav-panel { display:contents; }
        }
    </style>

    <nav class="vyt-anav" aria-label="Admin sections" data-admin-nav>
        <div class="vyt-anav-inner">
            <button type="button" class="vyt-anav-burger" aria-expanded="false" data-admin-nav-toggle>
                <span aria-hidden="true">☰</span> ADMIN MENU
            </button>

            <div class="vyt-anav-panel">
                <a href="{{ route('dashboard') }}" class="vyt-anav-top {{ request()->routeIs('dashboard') ? 'is-current' : '' }}"
                   @if (request()->routeIs('dashboard')) aria-current="page" @endif>Dashboard</a>

                @foreach ($adminSections as $section)
                    <details class="vyt-anav-sec {{ $section['current'] ? 'is-current' : '' }}" data-admin-nav-section>
                        <summary>{{ $section['label'] }}</summary>
                        <div class="vyt-anav-menu" role="menu">
                            @foreach ($section['items'] as $item)
                                <a role="menuitem" href="{{ route($item['route'], $item['query'] ?? []) }}"
                                   class="{{ $item['current'] ? 'is-current' : '' }}"
                                   @if ($item['current']) aria-current="page" @endif>{{ $item['label'] }}</a>
                            @endforeach
                        </div>
                    </details>
                @endforeach

                @if (auth()->user()?->hasPermission('users.create'))
                    <a href="{{ route('admin.users.create') }}" class="vyt-anav-cta">+ New user</a>
                @endif
            </div>
        </div>
    </nav>

    <script>
    (function () {
        var nav = document.querySelector('[data-admin-nav]');
        if (!nav) return;
        var sections = nav.querySelectorAll('[data-admin-nav-section]');
        var toggle = nav.querySelector('[data-admin-nav-toggle]');
        var desktop = window.matchMedia('(min-width: 1200px)');

        function align(section) {
            var menu = section.querySelector('.vyt-anav-menu');
            if (!menu || !desktop.matches) return;
            menu.classList.remove('align-right');
            var rect = menu.getBoundingClientRect();
            if (rect.right > window.innerWidth - 8) menu.classList.add('align-right');
        }

        sections.forEach(function (section) {
            section.addEventListener('toggle', function () {
                if (!section.open) return;
                if (desktop.matches) {
                    sections.forEach(function (other) { if (other !== section) other.open = false; });
                }
                align(section);
            });
            section.addEventListener('mouseenter', function () {
                if (desktop.matches) {
                    var menu = section.querySelector('.vyt-anav-menu');
                    menu.style.display = 'block'; align(section); menu.style.display = '';
                }
            });
        });

        if (toggle) {
            toggle.addEventListener('click', function () {
                var open = nav.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        document.addEventListener('click', function (e) {
            if (desktop.matches && !nav.contains(e.target)) {
                sections.forEach(function (s) { s.open = false; });
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                sections.forEach(function (s) { s.open = false; });
                nav.classList.remove('is-open');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            }
        });
    })();
    </script>
@endif
