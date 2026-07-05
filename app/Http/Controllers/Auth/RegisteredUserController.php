<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Services\Legal\LegalDocumentRegistry;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly LegalDocumentRegistry $legal) {}

    /**
     * Display the registration view.
     */
    public function create(): View
    {
        // Admin kill switch (Settings console, users.registration_open).
        abort_unless((bool) setting('users.registration_open', true), 403, 'Registration is temporarily closed.');

        return view('auth.register', [
            'legalDocs' => $this->legal->registrationRequired(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless((bool) setting('users.registration_open', true), 403, 'Registration is temporarily closed.');

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            // Required ToS + Privacy acceptance per FR-13. The form posts a
            // single boolean covering all required documents listed on the
            // page; we record one TermsAcceptance per current version below.
            'accept_terms' => ['accepted'],
        ]);

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // Record acceptance for every currently-required version so the
            // audit trail is complete from day one. If counsel later requires
            // the user to re-accept (e.g. amended ToS), the new version row
            // gets a different hash and EnsureCurrentTermsAccepted handles it.
            foreach ($this->legal->registrationRequired() as $version) {
                TermsAcceptance::create([
                    'user_id' => $user->id,
                    'terms_version_id' => $version->id,
                    'accepted_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
                ]);
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
