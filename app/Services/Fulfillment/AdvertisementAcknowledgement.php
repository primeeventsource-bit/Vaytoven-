<?php

namespace App\Services\Fulfillment;

/**
 * The exact words a member accepts.
 *
 * Stored in full on every acceptance record, with its version and hash, so
 * "what did they agree to" is answered from the record itself even after this
 * wording changes. Change the text → change the version. Never edit a
 * published version in place.
 */
final class AdvertisementAcknowledgement
{
    public const VERSION = '2026-09-16.v1';

    public const TEXT = 'Your advertisement is active. Please review your advertisement and confirm '
        .'that you have accessed and reviewed the advertising services provided through your Vaytoven account.';

    public const BUTTON = 'Accept & Confirm Advertisement';

    public static function hash(): string
    {
        return hash('sha256', self::VERSION."\n".self::TEXT);
    }
}
