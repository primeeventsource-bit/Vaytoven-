<?php

namespace App\Support\Storage;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Answers "is this file still on the disk?" for a page full of files, without
 * asking the bucket once per file.
 *
 * The bucket is remote. A single existence check against it measured 377ms on
 * production — fine once, ruinous in a loop. The listing editor asks three
 * times per photo (is the file there, is it there again for the larger preview,
 * is the pristine original there), so a listing with a dozen photos spent the
 * better part of a minute doing nothing but round trips, and the media library
 * asked once per asset across two hundred of them. Both timed out at the
 * gateway: 504, with the server still patiently waiting on S3.
 *
 * One recursive listing of the common prefix answers all of it. Object stores
 * return a thousand keys per request, so what was N round trips becomes one.
 *
 * Priming is explicit. A cache that fills itself invisibly is a cache that
 * lies somewhere you are not looking, and these answers decide whether a
 * member's photograph is shown or reported missing.
 */
class FilePresence
{
    /** "disk|path" => whether it is there. */
    private static array $known = [];

    /** Prefixes already listed, so a second prime on the same page is free. */
    private static array $primed = [];

    /**
     * Resolve every one of these paths in as few round trips as possible.
     *
     * @param  iterable<int, string|null>  $paths
     */
    public static function prime(?string $disk, iterable $paths): void
    {
        if ($disk === null || $disk === '') {
            return;
        }

        $wanted = [];

        foreach ($paths as $path) {
            $path = is_string($path) ? trim($path) : '';

            if ($path !== '' && ! isset(self::$known[$disk.'|'.$path])) {
                $wanted[$path] = true;
            }
        }

        if ($wanted === []) {
            return;
        }

        $prefix = self::commonPrefix(array_keys($wanted));

        // No shared prefix means these files are scattered across the whole
        // bucket, and listing the root to find a handful of them would cost
        // more than asking for each one. Leave them unprimed; the per-file
        // path still works.
        if ($prefix === '') {
            return;
        }

        if (isset(self::$primed[$disk.'|'.$prefix])) {
            return;
        }

        try {
            $found = Storage::disk($disk)->allFiles($prefix);
        } catch (Throwable) {
            // A listing failure must not take the page down with it. Callers
            // fall back to asking per file, which is slow but correct.
            return;
        }

        self::$primed[$disk.'|'.$prefix] = true;

        foreach ($found as $path) {
            self::$known[$disk.'|'.$path] = true;
        }

        // Anything asked for and not in the listing is genuinely absent. Worth
        // recording: "missing" is the answer that makes the page say so, and
        // re-asking the bucket for it on every render is the same round trip
        // this class exists to avoid.
        foreach (array_keys($wanted) as $path) {
            self::$known[$disk.'|'.$path] ??= false;
        }
    }

    /** Null when this path was never primed — ask the disk yourself. */
    public static function known(?string $disk, ?string $path): ?bool
    {
        if ($disk === null || $path === null || $path === '') {
            return null;
        }

        return self::$known[$disk.'|'.$path] ?? null;
    }

    /** Between tests, and after anything that writes or deletes. */
    public static function forget(): void
    {
        self::$known = [];
        self::$primed = [];
    }

    /**
     * The deepest directory containing all of these paths.
     *
     * Directory-wise, not character-wise: "properties/3" and "properties/34"
     * share the characters but not the folder, and listing the wrong prefix
     * would report a file missing that is sitting right there.
     */
    private static function commonPrefix(array $paths): string
    {
        $segments = null;

        foreach ($paths as $path) {
            $parts = explode('/', trim($path, '/'));
            array_pop($parts); // the filename itself is not a directory

            if ($segments === null) {
                $segments = $parts;

                continue;
            }

            $shared = [];

            foreach ($segments as $i => $segment) {
                if (($parts[$i] ?? null) !== $segment) {
                    break;
                }

                $shared[] = $segment;
            }

            $segments = $shared;

            if ($segments === []) {
                return '';
            }
        }

        return implode('/', $segments ?? []);
    }
}
