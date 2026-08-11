@extends('dashboard.layout')

@section('eyebrow', 'Account')
@section('title', 'Profile')

@push('head')
    <style>
        .vyt-prof { display:grid; gap:18px; max-width:640px; }
        .vyt-prof-card {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            padding:24px 26px;
        }
        .vyt-prof-card h2 { margin:0 0 4px; font-size:17px; }
        .vyt-prof-card .lede { margin:0 0 18px; font-size:13.5px; color:var(--muted); line-height:1.55; }
        .vyt-prof-field { margin-bottom:15px; }
        .vyt-prof-field label {
            display:block; font-size:12px; font-weight:600; letter-spacing:.06em;
            text-transform:uppercase; color:var(--muted); margin-bottom:5px;
        }
        .vyt-prof-field input {
            width:100%; padding:10px 13px; border:1px solid var(--line); border-radius:9px;
            font:inherit; font-size:14.5px; background:var(--bg); outline:none;
            transition:border-color .12s, box-shadow .12s;
        }
        .vyt-prof-field input:focus {
            border-color:var(--magenta); background:#fff;
            box-shadow:0 0 0 3px rgba(255,61,138,.14);
        }
        .vyt-prof-err { color:#b91c1c; font-size:12.5px; margin-top:5px; }
        .vyt-prof-actions { display:flex; align-items:center; gap:14px; margin-top:6px; }
        .vyt-prof-saved { font-size:13px; color:#047857; }
        .vyt-btn-danger {
            background:#b91c1c; color:#fff; border:0; padding:10px 20px;
            border-radius:999px; font:inherit; font-weight:600; font-size:14px; cursor:pointer;
        }
        .vyt-btn-danger:hover { background:#991b1b; }
        .vyt-prof-danger { border-color:#fecaca; }
        .vyt-prof-danger h2 { color:#b91c1c; }
    </style>
@endpush

@section('content')
    <section class="vyt-section">
        <div class="vyt-prof">
            <div class="vyt-prof-card">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="vyt-prof-card">
                @include('profile.partials.update-password-form')
            </div>

            <div class="vyt-prof-card vyt-prof-danger">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </section>
@endsection
