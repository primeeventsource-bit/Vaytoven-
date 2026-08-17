<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The screen a staff-created account lands on at first sign-in.
 *
 * Deliberately does NOT ask for the current password. The user is here because
 * somebody else set it — asking them to type it back proves nothing and just
 * makes them go and find the email it came in.
 */
class FirstPasswordChangeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.first-password-change');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ], [
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        // Refusing the issued password matters: otherwise the person who set
        // it up can retype it and the account is still shared.
        if (Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors([
                'password' => 'Choose a password different from the one you were given.',
            ]);
        }

        $user->forceFill([
            'password'             => $request->input('password'),   // hashed by cast
            'must_change_password' => false,
            'password_changed_at'  => now(),
        ])->save();

        // Any other session on this account was authenticated with the old
        // shared credential.
        auth()->logoutOtherDevices($request->input('password'));
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('status', 'Password updated. This account is now yours alone.');
    }
}
