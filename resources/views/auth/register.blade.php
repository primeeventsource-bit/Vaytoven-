@extends('layouts.auth-brand')

@section('title', 'Create account')
@section('brand_tag', 'Create account')
@section('card_class', 'is-wide')

@section('content')
    <h1 class="vyt-auth-h1">Create your account</h1>
    <p class="vyt-auth-sub">List your property, browse stays, and manage everything from one place.</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="vyt-grid-2">
            <div class="vyt-field">
                <label for="first_name">First name</label>
                <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}"
                       required autofocus autocomplete="given-name">
                @error('first_name') <div class="vyt-field-error">{{ $message }}</div> @enderror
            </div>

            <div class="vyt-field">
                <label for="last_name">Last name</label>
                <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}"
                       required autocomplete="family-name">
                @error('last_name') <div class="vyt-field-error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="vyt-field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   required autocomplete="username">
            @error('email') <div class="vyt-field-error">{{ $message }}</div> @enderror
        </div>

        <div class="vyt-field">
            <label for="phone">Phone number</label>
            <input id="phone" type="tel" name="phone" value="{{ old('phone') }}"
                   required autocomplete="tel" inputmode="tel" placeholder="+1 (555) 010-2030">
            @error('phone') <div class="vyt-field-error">{{ $message }}</div> @enderror
        </div>

        <div class="vyt-grid-2">
            <div class="vyt-field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password"
                       required autocomplete="new-password">
                @error('password') <div class="vyt-field-error">{{ $message }}</div> @enderror
            </div>

            <div class="vyt-field">
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation"
                       required autocomplete="new-password">
                @error('password_confirmation') <div class="vyt-field-error">{{ $message }}</div> @enderror
            </div>
        </div>

        {{-- Required ToS + Privacy acceptance (FR-13). The documents come from
             LegalDocumentRegistry so the links always point at the versions
             the acceptance record will reference. --}}
        <label class="vyt-consent" for="accept_terms">
            <input id="accept_terms" name="accept_terms" type="checkbox" value="1" required>
            <span>
                I have read and agree to the
                @foreach (($legalDocs ?? []) as $i => $doc)
                    <a href="{{ $doc->content_url }}" target="_blank" rel="noopener">{{ ucwords(str_replace('_', ' ', $doc->kind)) }}</a>@if ($i + 1 < count($legalDocs)), @endif
                @endforeach.
            </span>
        </label>
        @error('accept_terms') <div class="vyt-field-error" style="margin-top:-14px; margin-bottom:16px;">{{ $message }}</div> @enderror

        <button type="submit" class="vyt-auth-cta is-caps">Create account</button>
    </form>

    <div class="vyt-auth-foot">
        Already have an account? <a href="{{ route('login') }}">Sign in</a>
    </div>
@endsection
