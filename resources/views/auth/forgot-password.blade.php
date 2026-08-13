@extends('layouts.auth-brand')

@section('title', 'Reset password')
@section('brand_tag', 'Reset')

@section('content')
    <h1 class="vyt-auth-h1">Reset your password</h1>
    <p class="vyt-auth-sub">
        Enter your email and we'll send you a secure link to choose a new password.
    </p>

    @if (session('status'))
        <div class="vyt-alert-status">{{ session('status') }}</div>
    @endif

    {{-- Shown when the app cannot deliver mail. Saying so up front is the
         whole point: the previous behaviour accepted the address, reported
         success, and sent nothing, so people waited on an email that was never
         coming and assumed their account was broken. No infrastructure detail
         here — the operator gets that from /health and the logs. --}}
    @if ($mailOutage ?? false)
        <div class="vyt-alert-status" style="background:#fef2f2; border-color:#fecaca; color:#991b1b;">
            <strong>Password reset emails are unavailable right now.</strong><br>
            Please email <a href="mailto:{{ setting('general.support_email', 'contact@vaytoven.com') }}"
               style="color:inherit; text-decoration:underline;">{{ setting('general.support_email', 'contact@vaytoven.com') }}</a>
            or call <a href="tel:+18777829868" style="color:inherit; text-decoration:underline;">(877) 782-9868</a>
            and we'll get you back into your account.
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="vyt-field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   required autofocus autocomplete="email">
            @error('email') <div class="vyt-field-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="vyt-auth-cta">Email password reset link</button>
    </form>

    <div class="vyt-auth-foot">
        Remembered it? <a href="{{ route('login') }}">Back to sign in</a>
    </div>
@endsection
