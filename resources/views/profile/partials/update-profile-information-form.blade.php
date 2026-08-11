<h2>Profile information</h2>
<p class="lede">Update your name, contact details, and email address.</p>

<form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>

<form method="post" action="{{ route('profile.update') }}">
    @csrf
    @method('patch')

    <div class="vyt-prof-field">
        <label for="name">Full name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}"
               required autofocus autocomplete="name">
        @error('name') <div class="vyt-prof-err">{{ $message }}</div> @enderror
    </div>

    <div class="vyt-prof-field">
        <label for="phone">Phone number</label>
        <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}"
               autocomplete="tel" inputmode="tel" placeholder="+1 (555) 010-2030">
        @error('phone') <div class="vyt-prof-err">{{ $message }}</div> @enderror
    </div>

    <div class="vyt-prof-field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}"
               required autocomplete="username">
        @error('email') <div class="vyt-prof-err">{{ $message }}</div> @enderror

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <p style="font-size:13px; color:var(--muted); margin:8px 0 0;">
                Your email address is unverified.
                <button form="send-verification" type="submit"
                        style="background:none; border:0; padding:0; font:inherit; color:var(--magenta); font-weight:500; cursor:pointer;">
                    Re-send the verification email
                </button>
            </p>

            @if (session('status') === 'verification-link-sent')
                <p style="font-size:13px; color:#047857; margin:6px 0 0;">
                    A new verification link has been sent to your email address.
                </p>
            @endif
        @endif
    </div>

    <div class="vyt-prof-actions">
        <button type="submit" class="vyt-btn">Save changes</button>
        @if (session('status') === 'profile-updated')
            <span class="vyt-prof-saved">Saved.</span>
        @endif
    </div>
</form>
