<h2>Password</h2>
<p class="lede">Use a long, unique password you don't use anywhere else.</p>

<form method="post" action="{{ route('password.update') }}">
    @csrf
    @method('put')

    <div class="vyt-prof-field">
        <label for="update_password_current_password">Current password</label>
        <input id="update_password_current_password" name="current_password" type="password"
               autocomplete="current-password">
        @if ($errors->updatePassword->get('current_password'))
            <div class="vyt-prof-err">{{ $errors->updatePassword->first('current_password') }}</div>
        @endif
    </div>

    <div class="vyt-prof-field">
        <label for="update_password_password">New password</label>
        <input id="update_password_password" name="password" type="password" autocomplete="new-password">
        @if ($errors->updatePassword->get('password'))
            <div class="vyt-prof-err">{{ $errors->updatePassword->first('password') }}</div>
        @endif
    </div>

    <div class="vyt-prof-field">
        <label for="update_password_password_confirmation">Confirm new password</label>
        <input id="update_password_password_confirmation" name="password_confirmation" type="password"
               autocomplete="new-password">
        @if ($errors->updatePassword->get('password_confirmation'))
            <div class="vyt-prof-err">{{ $errors->updatePassword->first('password_confirmation') }}</div>
        @endif
    </div>

    <div class="vyt-prof-actions">
        <button type="submit" class="vyt-btn">Update password</button>
        @if (session('status') === 'password-updated')
            <span class="vyt-prof-saved">Saved.</span>
        @endif
    </div>
</form>
