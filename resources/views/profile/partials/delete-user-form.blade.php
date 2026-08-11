<h2>Delete account</h2>
<p class="lede">
    Deleting your account permanently removes your profile and access. Download anything you want
    to keep first. Bookings and payment records are retained where we're legally required to keep
    them.
</p>

{{-- Native <details> rather than the Breeze Alpine modal: this page no longer
     loads Alpine, and a confirmation that works without JavaScript is the
     right default for a destructive action. --}}
<details @if ($errors->userDeletion->isNotEmpty()) open @endif>
    <summary style="cursor:pointer; color:#b91c1c; font-weight:600; font-size:14px;">
        Delete my account
    </summary>

    <form method="post" action="{{ route('profile.destroy') }}" style="margin-top:16px;">
        @csrf
        @method('delete')

        <div class="vyt-prof-field">
            <label for="delete_password">Confirm your password to continue</label>
            <input id="delete_password" name="password" type="password" placeholder="Your password"
                   autocomplete="current-password">
            @if ($errors->userDeletion->get('password'))
                <div class="vyt-prof-err">{{ $errors->userDeletion->first('password') }}</div>
            @endif
        </div>

        <button type="submit" class="vyt-btn-danger">Permanently delete account</button>
    </form>
</details>
