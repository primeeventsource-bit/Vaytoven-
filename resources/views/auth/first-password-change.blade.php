@extends('layouts.auth-brand')

@section('title', 'Set your password')
@section('brand_tag', 'Secure')

@section('content')
    <h1 class="vyt-auth-h1">Set your own password</h1>
    <p class="vyt-auth-sub">
        This account was created for you, so the password you signed in with is
        known to someone else. Choose your own to finish setting it up.
    </p>

    <form method="POST" action="{{ route('password.first-change.store') }}">
        @csrf

        <div class="vyt-field">
            <label for="password">New password</label>
            <input id="password" type="password" name="password" required autofocus
                   autocomplete="new-password">
            @error('password') <div class="vyt-field-error">{{ $message }}</div> @enderror
            <div style="font-size:12.5px;color:var(--muted);margin-top:6px;">
                At least 10 characters, with letters and numbers.
            </div>
        </div>

        <div class="vyt-field">
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation"
                   required autocomplete="new-password">
        </div>

        <button type="submit" class="vyt-auth-cta">SAVE PASSWORD</button>
    </form>

    <div class="vyt-auth-foot">
        Not your account?
        <form method="POST" action="{{ route('logout') }}" style="display:inline;">
            @csrf
            <button type="submit" style="background:none;border:0;padding:0;font:inherit;cursor:pointer;text-decoration:underline;">
                Sign out
            </button>
        </form>
    </div>
@endsection
