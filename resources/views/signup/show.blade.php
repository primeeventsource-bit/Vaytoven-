<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Subscribe · Vaytoven</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @include('partials.brand-styles')
    <style>
        body { background: var(--bg); }
        .signup-shell {
            max-width: 560px; margin: 0 auto; padding: 32px 24px 80px;
        }
        .signup-card {
            background: #fff; border: 1px solid var(--line); border-radius: 18px;
            padding: clamp(28px, 4vw, 44px);
            box-shadow: 0 4px 16px -8px rgba(123,44,191,.12);
        }
        .signup-eyebrow {
            font-size: 12px; letter-spacing:.12em; text-transform: uppercase;
            color: var(--muted); font-weight: 600;
        }
        .signup-title {
            font-family:'Fraunces',serif; font-size: clamp(28px, 4vw, 38px);
            font-weight: 600; letter-spacing: -.02em;
            margin: 8px 0 14px;
        }
        .signup-lede { color: var(--muted); font-size: 15px; line-height: 1.55; margin: 0 0 28px; }
        .signup-field { margin-bottom: 18px; }
        .signup-field label {
            display: block; font-size: 11px; letter-spacing:.08em;
            text-transform: uppercase; color: var(--muted);
            font-weight: 600; margin-bottom: 6px;
        }
        .signup-field input {
            width: 100%; padding: 12px 14px;
            border: 1px solid var(--line); border-radius: 10px;
            font-size: 15px; outline: none; background: var(--bg);
            font-family: 'Geist', sans-serif;
            transition: border-color .12s, background .12s;
        }
        .signup-field input:focus { border-color: var(--magenta); background: #fff; }
        .signup-field .req { color: var(--magenta); }
        .signup-error { color:#b91c1c; font-size: 12.5px; margin-top: 6px; }
        .signup-submit {
            width: 100%; padding: 14px 22px; border-radius: 999px;
            background: var(--gradient); color: #fff; border: 0;
            font-family: 'Geist', sans-serif; font-weight: 600; font-size: 15px;
            cursor: pointer; transition: transform .12s ease;
            box-shadow: 0 8px 24px -8px rgba(214,51,132,.4);
        }
        .signup-submit:hover { transform: translateY(-1px); }
        .signup-fineprint {
            font-size: 12px; color: var(--muted); margin: 18px 0 0; text-align: center;
        }
        .signup-success {
            background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857;
            padding: 14px 18px; border-radius: 12px; margin-bottom: 24px;
            font-size: 14px;
        }
        .signup-back { font-size: 13px; color: var(--muted); }
    </style>
</head>
<body>
    @include('partials.top-nav', ['current' => 'signup'])

    <main class="signup-shell">
        <a href="/" class="signup-back">← Back to home</a>

        <div class="signup-card" style="margin-top: 18px;">
            @if (session('subscriber_success'))
                <div class="signup-success">✓ {{ session('subscriber_success') }}</div>
            @endif

            <div class="signup-eyebrow">Join the list</div>
            <h1 class="signup-title">Get early access &amp; offers</h1>
            <p class="signup-lede">
                Drop your details below. We'll send the occasional update on new destinations, openings in the Managed Listing Program, and traveler-only promotions. Unsubscribe with one click in any email.
            </p>

            <form method="POST" action="{{ route('signup.store') }}">
                @csrf

                <div class="signup-field">
                    <label for="full_name">Full name <span class="req">*</span></label>
                    <input id="full_name" name="full_name" type="text" required autocomplete="name" value="{{ old('full_name') }}" placeholder="Jane Doe">
                    @error('full_name') <p class="signup-error">{{ $message }}</p> @enderror
                </div>

                <div class="signup-field">
                    <label for="email">Email <span class="req">*</span></label>
                    <input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}" placeholder="jane@example.com">
                    @error('email') <p class="signup-error">{{ $message }}</p> @enderror
                </div>

                <div class="signup-field">
                    <label for="phone">Phone <span style="color:var(--muted); font-weight:500; text-transform:none; letter-spacing:0;">(optional)</span></label>
                    <input id="phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone') }}" placeholder="+1 555 555 0100">
                    @error('phone') <p class="signup-error">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="signup-submit" data-track-audience="traveler" data-track-cta="newsletter_subscribe">
                    Subscribe
                </button>

                <p class="signup-fineprint">
                    By subscribing you agree to our <a href="{{ route('legal.privacy') }}">Privacy Policy</a>. We'll never share your details with third parties.
                </p>
            </form>
        </div>
    </main>

    <script src="/vyt-track.js" defer></script>
</body>
</html>
