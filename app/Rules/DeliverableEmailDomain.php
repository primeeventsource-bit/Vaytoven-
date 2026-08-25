<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Rejects sign-ups on domains that cannot receive mail.
 *
 * Not a spam measure and not a guess about who is real. These are the top-level
 * domains the IETF reserved precisely so they would never resolve — .test,
 * .example, .invalid, .localhost (RFC 6761) and .local (RFC 6762). No address
 * under them can take delivery, from anyone, ever. An account created on one
 * can never verify its email, reset a password, or be contacted.
 *
 * They exist for test suites, which is exactly the problem. A copy of this
 * codebase kept its inherited Playwright base URL and registered 51 accounts
 * here on mybluebeacon.test before anyone noticed, each one firing a
 * first-sign-in notice to the office. Fixing that copy stops that copy; this
 * stops the next one, and the one after, without anybody having to know it
 * exists.
 *
 * Deployed environments only. Locally the reserved domains are the correct
 * thing to sign up with — our own end-to-end suite does it deliberately — and
 * blocking them there would break the tests to punish the tests.
 */
class DeliverableEmailDomain implements ValidationRule
{
    /**
     * Reserved by RFC 6761 and RFC 6762. Guaranteed never to resolve.
     *
     * @var list<string>
     */
    public const RESERVED_TLDS = ['test', 'example', 'invalid', 'localhost', 'local'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->applies()) {
            return;
        }

        if (! is_string($value) || ! str_contains($value, '@')) {
            return; // shape is the `email` rule's job, not ours
        }

        if (self::isReserved($value)) {
            $fail('Enter an email address that can receive mail. That domain is reserved for testing and will never accept delivery.');
        }
    }

    /** Whether an address sits on a domain that can never take delivery. */
    public static function isReserved(string $email): bool
    {
        $domain = Str::of($email)->afterLast('@')->lower()->trim()->toString();

        if ($domain === '') {
            return false;
        }

        // The last label, and the whole domain for a bare "user@localhost".
        $tld = Str::afterLast($domain, '.');

        return in_array($tld, self::RESERVED_TLDS, true)
            || in_array($domain, self::RESERVED_TLDS, true);
    }

    /**
     * Enforced on deployed environments only.
     *
     * Every cloud environment runs as production, so this covers all of them
     * while leaving local development and the test suite free to use the
     * reserved domains for what they are for.
     */
    private function applies(): bool
    {
        return app()->environment('production');
    }
}
