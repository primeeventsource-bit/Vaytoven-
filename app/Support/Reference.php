<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Customer-quotable reference codes, e.g. VYT-C-8F2A1B.
 *
 * Deliberately not the primary key: an incrementing id tells anyone who reads
 * it how many records exist, and it is easy to mistype over the phone. The
 * alphabet drops the characters people confuse when reading a code aloud
 * (0/O, 1/I/L), which matters because these get read aloud.
 */
final class Reference
{
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const LENGTH = 6;

    /**
     * @param  string  $prefix  One letter identifying the record type: C
     *                          contact, S support, J job application.
     * @param  callable(string): bool  $exists  Collision check against the table.
     */
    public static function generate(string $prefix, callable $exists): string
    {
        do {
            $code = 'VYT-'.$prefix.'-'.self::randomBlock();
        } while ($exists($code));

        return $code;
    }

    private static function randomBlock(): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }

    /** Slug helper shared by job openings and press releases. */
    public static function uniqueSlug(string $title, callable $exists): string
    {
        $base = Str::slug($title) ?: 'item';
        $slug = $base;
        $n = 2;

        while ($exists($slug)) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
