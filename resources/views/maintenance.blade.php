<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ setting('general.site_name', 'Vaytoven Rentals') }} — be right back</title>
    <style>
        :root { --gradient: linear-gradient(135deg, #ec4899, #d946ef, #a855f7); }
        * { box-sizing: border-box; margin: 0; }
        body {
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            background: #fdf6f0; color: #1f2937; padding: 24px;
        }
        .card { max-width: 460px; text-align: center; }
        .pin {
            width: 56px; height: 56px; margin: 0 auto 22px; border-radius: 50% 50% 50% 4px;
            background: var(--gradient); transform: rotate(-45deg);
        }
        h1 {
            font-size: 28px; margin-bottom: 12px;
            background: var(--gradient); -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        p { font-size: 15px; line-height: 1.6; color: #6b7280; }
    </style>
</head>
<body>
    <div class="card">
        <div class="pin" aria-hidden="true"></div>
        <h1>Be right back</h1>
        <p>{{ $message }}</p>
    </div>
</body>
</html>
