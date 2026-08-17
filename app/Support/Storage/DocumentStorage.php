<?php

namespace App\Support\Storage;

use Illuminate\Support\Facades\Config;

/**
 * Will an uploaded file still be here next week?
 *
 * On Laravel Cloud the `local` disk lives inside the container filesystem, so
 * anything written there disappears on the next deploy. A document store built
 * on it would accept a signed agreement, show it in the list, and lose it the
 * next time somebody pushed — the worst possible failure for a record whose
 * entire purpose is to exist later during a dispute.
 *
 * So uploads are refused unless the disk is durable, rather than accepted and
 * quietly discarded. Same reasoning as MailDeliverability: a subsystem that
 * cannot do its job should say so, not fail silently.
 *
 * The `production` environment already has a Cloud object-storage disk
 * attached; `main`, which serves the public site, does not. That is drift, not
 * a missing capability.
 */
class DocumentStorage
{
    /** Drivers whose contents survive a redeploy. */
    private const DURABLE_DRIVERS = ['s3', 'gcs', 'azure'];

    public static function disk(): string
    {
        return (string) Config::get('filesystems.default');
    }

    public static function driver(): ?string
    {
        return Config::get('filesystems.disks.'.self::disk().'.driver');
    }

    /**
     * True when a file written now will still be readable after a deploy.
     *
     * Local storage is treated as durable in local development and testing —
     * there is no deploy to lose it to, and requiring object storage to run
     * the test suite would be absurd.
     */
    public static function isDurable(): bool
    {
        if (in_array(self::driver(), self::DURABLE_DRIVERS, true)) {
            return true;
        }

        return app()->environment('local', 'testing');
    }

    /** Operator-facing explanation. Never shown to a member. */
    public static function reason(): ?string
    {
        if (self::isDurable()) {
            return null;
        }

        return sprintf(
            'The "%s" disk uses the %s driver in the %s environment. Files written there are '
            .'inside the container and are lost on the next deploy. Attach a Cloud disk to this '
            .'environment and set FILESYSTEM_DISK to it.',
            self::disk(),
            self::driver() ?? 'unknown',
            app()->environment(),
        );
    }
}
