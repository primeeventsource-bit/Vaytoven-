<?php

namespace App\Support\GeoIp;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Where the MaxMind database lives, and how it survives a deploy.
 *
 * The reader needs a real filesystem path — it memory-maps the file — and the
 * container's filesystem is wiped on every deploy. So the file is kept on the
 * durable disk (object storage) and copied down to local storage the first
 * time it is needed.
 *
 * The copy is done once per container, not per request: the file is ~60MB and
 * fetching it on every lookup would be slower than not having geo at all.
 *
 * If anything here fails the answer is "no database", which sends the resolver
 * back to Cloudflare headers. Country-level geo is worse than city-level geo
 * and much better than a 500 on every page view.
 */
class GeoIpDatabase
{
    public const EDITION = 'GeoLite2-City';

    /** Key on the durable disk. */
    public const REMOTE_KEY = 'geoip/GeoLite2-City.mmdb';

    /**
     * The reader's path.
     *
     * MAXMIND_MMDB_PATH still wins when set, so a machine with the database
     * installed system-wide keeps working without touching object storage.
     */
    public static function localPath(): string
    {
        $configured = Config::get('services.maxmind.mmdb_path');

        if ($configured) {
            return $configured;
        }

        return storage_path('app/geoip/'.self::EDITION.'.mmdb');
    }

    public static function existsLocally(): bool
    {
        $path = self::localPath();

        return is_readable($path) && filesize($path) > 0;
    }

    /**
     * Make the database available on local disk, fetching it from the durable
     * disk if this container has not done so yet.
     *
     * Returns the path, or null when there is nothing to read.
     */
    public static function ensureAvailable(): ?string
    {
        if (self::existsLocally()) {
            return self::localPath();
        }

        try {
            $disk = Storage::disk(\App\Support\Storage\DocumentStorage::disk());

            if (! $disk->exists(self::REMOTE_KEY)) {
                return null;
            }

            $path = self::localPath();
            @mkdir(dirname($path), 0775, true);

            // Written to a temporary name and renamed, so a container that
            // reads while another is still downloading never sees a half file.
            $temp = $path.'.'.getmypid().'.part';

            $stream = $disk->readStream(self::REMOTE_KEY);

            if (! $stream) {
                return null;
            }

            $handle = fopen($temp, 'wb');
            stream_copy_to_stream($stream, $handle);
            fclose($handle);
            fclose($stream);

            rename($temp, $path);

            return self::existsLocally() ? $path : null;
        } catch (Throwable $e) {
            Log::warning('geoip: could not hydrate the database from durable storage.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** When the stored database was built, for the operator screen. */
    public static function builtAt(): ?string
    {
        if (! self::existsLocally()) {
            return null;
        }

        $timestamp = @filemtime(self::localPath());

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    public static function sizeForHumans(): ?string
    {
        if (! self::existsLocally()) {
            return null;
        }

        return number_format(filesize(self::localPath()) / 1048576, 1).' MB';
    }
}
