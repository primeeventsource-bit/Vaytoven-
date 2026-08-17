{{--
  Unified top nav across every public-facing branded surface.

  Order is fixed (Stay → Become a Host → Members → Pricing → Terms → Signup →
  Login) so the user sees the same hierarchy on every page. The optional
  $current var highlights the active tab — pass 'stay', 'become-a-host',
  'members', 'pricing', 'terms', 'signup', or 'login'. Auth'd users see their
  dashboard link instead of Login/Signup.

  Pricing is the entry to Member Services activation. It was reachable only
  from a line at the foot of /members when it shipped, which made it an
  orphan — live, but invisible to anyone who did not already know the URL.

  Terms links to the named `terms` route, which is the friendly /terms alias
  for the canonical /legal/tos document. Nav changes are safe for the legal
  hash: LegalDocumentRegistry hashes only the <main class="legal-shell">
  region, precisely so page chrome cannot mint a new terms version.
--}}
@php
    $current = $current ?? null;
    $isAuthd = auth()->check();
@endphp

<style>
    .vyt-topnav {
        background: #fff; border-bottom: 1px solid var(--line);
        padding: 14px 24px;
        display: flex; align-items: center; gap: 18px;
        position: sticky; top: 0; z-index: 30;
    }
    .vyt-topnav .brand {
        display: flex; align-items: center; gap: 10px;
        color: var(--ink); font-weight: 600; font-size: 16px;
        text-decoration: none;
    }
    .vyt-topnav .brand:hover { text-decoration: none; }
    .vyt-topnav .brand svg { width: 30px; height: 30px; }
    .vyt-topnav-links {
        display: flex; gap: 4px; margin-left: 12px; flex-wrap: wrap;
    }
    .vyt-topnav-links a {
        color: var(--ink); font-size: 14px; font-weight: 500;
        padding: 8px 14px; border-radius: 999px;
        transition: background .12s, color .12s;
    }
    .vyt-topnav-links a:hover { background: #faf7ff; color: var(--purple); text-decoration: none; }
    .vyt-topnav-links a.is-current {
        background: var(--gradient); color: #fff;
    }
    .vyt-topnav-links a.is-current:hover { color: #fff; background: var(--gradient); opacity: .92; }
    .vyt-topnav-spacer { flex: 1; }
    .vyt-topnav-cta {
        background: var(--gradient); color: #fff !important;
        padding: 8px 18px; border-radius: 999px; font-size: 14px; font-weight: 600;
        transition: transform .12s ease;
    }
    .vyt-topnav-cta:hover { transform: translateY(-1px); text-decoration: none; }
    .vyt-topnav-mute { color: var(--muted); }
    @media (max-width: 720px) {
        .vyt-topnav { padding: 10px 16px; gap: 10px; }
        .vyt-topnav-links a { padding: 6px 10px; font-size: 13px; }
    }
</style>

<nav class="vyt-topnav" role="navigation" aria-label="Primary">
    <a href="/" class="brand" aria-label="Vaytoven home">
        <svg viewBox="0 0 64 64">
            <defs>
                <linearGradient id="vyt-nav-grad" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FF3D8A"/>
                    <stop offset="50%" stop-color="#D63384"/>
                    <stop offset="100%" stop-color="#7B2CBF"/>
                </linearGradient>
            </defs>
            <path fill="url(#vyt-nav-grad)" d="M10 8h12l10 30 10-30h12L34 56h-8z"/>
            <circle cx="48" cy="14" r="8" fill="url(#vyt-nav-grad)"/>
            <circle cx="48" cy="14" r="3" fill="#fff"/>
        </svg>
        <span>Vaytoven</span>
    </a>

    <div class="vyt-topnav-links">
        <a href="{{ route('properties.index') }}" class="{{ $current === 'stay' ? 'is-current' : '' }}" data-track-audience="traveler" data-track-cta="topnav_stay">Stay</a>
        <a href="{{ route('hosts.show') }}" class="{{ $current === 'become-a-host' ? 'is-current' : '' }}" data-track-audience="host" data-track-cta="topnav_become_host">Become a Host</a>
        <a href="{{ route('members.show') }}" class="{{ $current === 'members' ? 'is-current' : '' }}" data-track-audience="member" data-track-cta="topnav_members">Members</a>
        <a href="{{ route('member-services.show') }}" class="{{ $current === 'pricing' ? 'is-current' : '' }}" data-track-audience="member" data-track-cta="topnav_pricing">Pricing</a>
        <a href="{{ route('terms') }}" class="{{ $current === 'terms' ? 'is-current' : '' }}" data-track-cta="topnav_terms">Terms</a>
    </div>

    <div class="vyt-topnav-spacer"></div>

    <div class="vyt-topnav-links">
        @if ($isAuthd)
            <a href="{{ route('dashboard') }}" data-track-cta="topnav_dashboard">Dashboard</a>
            <form method="POST" action="{{ route('logout') }}" style="margin:0; display:inline;">
                @csrf
                <button type="submit" class="vyt-topnav-mute" style="background:none; border:0; padding:8px 14px; font: inherit; cursor: pointer;">Sign out</button>
            </form>
        @else
            <a href="{{ route('signup.show') }}" class="{{ $current === 'signup' ? 'is-current' : '' }}" data-track-cta="topnav_signup">Signup</a>
            <a href="{{ route('login') }}" class="vyt-topnav-cta" data-track-cta="topnav_login">Login</a>
        @endif
    </div>
</nav>
