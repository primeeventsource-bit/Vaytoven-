@extends('layouts.auth-brand')

@section('title', 'Confirm password')
@section('brand_tag', 'Security check')

@section('content')
    <h1 class="vyt-auth-h1">Confirm it's you</h1>
    <p class="vyt-auth-sub">This is a secure area. Please re-enter your password to continue.</p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="vyt-field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password"
                   required autofocus autocomplete="current-password">
            @error('password') <div class="vyt-field-error">{{ $message }}</div> @enderror
        </div>

        <div style="height:8px;"></div>
        <button type="submit" class="vyt-auth-cta">Confirm</button>
    </form>
@endsection
