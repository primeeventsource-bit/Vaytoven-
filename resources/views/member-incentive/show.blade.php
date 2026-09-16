<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your {{ $incentive->name }} · Vaytoven</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,500;1,400&family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { margin:0; background:#FBF8F3; }
        .wrap { max-width:900px; margin:0 auto; }
    </style>
</head>
<body>
<div class="wrap">
    @include('member-incentive._artwork', ['offer' => $incentive, 'withButtons' => true])
</div>
</body>
</html>
