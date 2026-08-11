@extends('layouts.auth-brand')

@section('title', 'Verify your email')
@section('brand_tag', 'Verify email')

@section('content')
    <h1 class="vyt-auth-h1">Check your inbox</h1>
    <p class="vyt-auth-sub">
        We've sent a verification link to your email address. Click it to finish setting up your
        Vaytoven account. If it hasn't arrived, we can send another.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="vyt-alert-status">
            A new verification link is on its way to the address you registered with.
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="vyt-auth-cta">Resend verification email</button>
    </form>

    <div class="vyt-auth-foot">
        <form method="POST" action="{{ route('logout') }}" style="display:inline;">
            @csrf
            <button type="submit"
                    style="background:none; border:0; padding:0; font:inherit; color:var(--magenta); font-weight:500; cursor:pointer;">
                Sign out
            </button>
        </form>
    </div>
@endsection
