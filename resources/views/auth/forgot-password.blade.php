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
