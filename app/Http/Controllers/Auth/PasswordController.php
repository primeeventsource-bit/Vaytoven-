<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        app(\App\Services\Tracking\ActivityRecorder::class)->record(
            \App\Enums\ActivityType::PasswordReset, $request, subjectType: 'user',
            subjectReference: (string) $request->user()->id, result: 'completed',
            metadata: ['change' => 'password_changed'], actor: $request->user(),
        );

        return back()->with('status', 'password-updated');
    }
}
