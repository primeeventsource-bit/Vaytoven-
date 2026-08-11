@extends('layouts.auth-brand')

@section('title', 'Reset password')
@section('brand_tag', 'Reset password')

@section('content')
    <h1 class="vyt-auth-h1">Choose a new password</h1>
    <p class="vyt-auth-sub">Pick something you haven't used elsewhere.</p>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="vyt-field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}"
                   required autofocus autocomplete="username">
            @error('email') <div class="vyt-field-error">{{ $message }}</div> @enderror
        </div>

        <div class="vyt-field">
            <label for="password">New password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
            @error('password') <div class="vyt-field-error">{{ $message }}</div> @enderror
        </div>

        <div class="vyt-field">
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation"
                   required autocomplete="new-password">
            @error('password_confirmation') <div class="vyt-field-error">{{ $message }}</div> @enderror
        </div>

        <div style="height:8px;"></div>
        <button type="submit" class="vyt-auth-cta">Reset password</button>
    </form>

    <div class="vyt-auth-foot">
        Remembered it? <a href="{{ route('login') }}">Sign in</a>
    </div>
@endsection
