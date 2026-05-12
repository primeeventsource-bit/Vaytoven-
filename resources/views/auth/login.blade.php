@extends('layouts.auth-brand')

@section('title', 'Sign in')
@section('brand_tag', 'Sign in')

@section('content')
    <h1 class="vyt-auth-h1">Welcome back</h1>
    <p class="vyt-auth-sub">Sign in to your Vaytoven account.</p>

    @if (session('status'))
        <div class="vyt-alert-status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="vyt-field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username">
            @error('email') <div class="vyt-field-error">{{ $message }}</div> @enderror
        </div>

        <div class="vyt-field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password"
                   required autocomplete="current-password">
            @error('password') <div class="vyt-field-error">{{ $message }}</div> @enderror
        </div>

        <div class="vyt-row-between">
            <label>
                <input type="checkbox" name="remember">
                Remember me
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}">Forgot your password?</a>
            @endif
        </div>

        <button type="submit" class="vyt-auth-cta">Sign in</button>
    </form>

    <div class="vyt-auth-foot">
        New to Vaytoven? <a href="{{ route('register') }}">Create an account</a>
    </div>
@endsection
