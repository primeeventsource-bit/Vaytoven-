<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user      = $request->user();
        $validated = $request->validated();
        $addressFields = \App\Services\Members\AddressOnFileUpdater::FIELDS;

        $user->fill(\Illuminate\Support\Arr::except($validated, $addressFields));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $changed = array_keys($user->getDirty());
        $user->save();

        // An important account change, recorded with the member's own device
        // and location. Field names only — the values are on the account.
        if ($changed !== []) {
            app(\App\Services\Tracking\ActivityRecorder::class)->record(
                \App\Enums\ActivityType::ProfileUpdated,
                $request,
                subjectType: 'user',
                subjectReference: (string) $user->id,
                result: 'completed',
                metadata: ['changed' => array_values(array_diff($changed, ['email_verified_at', 'updated_at']))],
                actor: $user,
            );
        }

        app(\App\Services\Members\AddressOnFileUpdater::class)->apply($user, $validated, $user, $request);

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
