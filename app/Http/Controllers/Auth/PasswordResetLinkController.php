<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Mail\MailDeliverability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password', [
            // The form warns instead of pretending when mail cannot go out.
            'mailOutage' => ! MailDeliverability::isDeliverable(),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * Three things the stock Breeze version got wrong, all of which mattered
     * here because mail was silently disabled in production for months:
     *
     * 1. It reported success when no mail could be sent. Password::sendResetLink
     *    returns RESET_LINK_SENT for the `log` transport, so the page said
     *    "we have emailed your password reset link" to every user while the
     *    link went to a log file. Telling someone to check an inbox that will
     *    never receive anything is worse than an error — they wait, retry, and
     *    conclude their account is broken.
     *
     * 2. It let transport failures escape as a 500. Wrong SMTP credentials
     *    throw at send time, and the user got a white error page with no idea
     *    whether to try again.
     *
     * 3. It answered differently for known and unknown addresses, which turns
     *    the form into an account-enumeration oracle: submit an address, learn
     *    from the response whether it has an account here. Now every outcome
     *    that is not an outage produces the same neutral confirmation.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Refuse before generating a token. A token minted here would be a
        // valid reset credential sitting in the database that nobody can ever
        // receive, and it would invalidate any earlier working link.
        if (! MailDeliverability::isDeliverable()) {
            Log::critical('Password reset requested but mail cannot be delivered.', [
                'reason' => MailDeliverability::reason(),
            ]);

            return back()->withInput($request->only('email'))->withErrors([
                'email' => __('We cannot send password reset emails at the moment. '
                    .'Please contact :email and we will help you get back in.', [
                        'email' => setting('general.support_email', 'contact@vaytoven.com'),
                    ]),
            ]);
        }

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (TransportExceptionInterface|Throwable $e) {
            // The reason is logged, never shown: it names hosts and can echo
            // provider responses that quote credentials.
            Log::error('Password reset mail failed to send.', [
                'exception' => $e->getMessage(),
            ]);

            return back()->withInput($request->only('email'))->withErrors([
                'email' => __('We could not send that email just now. Please try again in a '
                    .'few minutes, or contact :email.', [
                        'email' => setting('general.support_email', 'contact@vaytoven.com'),
                    ]),
            ]);
        }

        // Throttling is the one non-outage case worth reporting honestly: the
        // user has a link already and telling them to wait is actionable.
        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => __($status),
            ]);
        }

        // Uniform response for sent AND for "no such user" — see (3) above.
        return back()->with('status', __(Password::RESET_LINK_SENT));
    }
}
