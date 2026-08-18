<?php

namespace App\Http\Controllers;

use App\Models\PropertyPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves an uploaded listing photo.
 *
 * The bucket is private, so images cannot be linked directly. The alternative
 * was a signed URL, which expires — useless for anything cached, shared, or
 * put in front of a CDN, and it would make every page render mint a fresh
 * batch of signatures.
 *
 * Public on purpose: these are advertisements. What is NOT public is the
 * original upload, which keeps its EXIF and is only reachable through the
 * admin screens.
 */
class PropertyPhotoStreamController extends Controller
{
    /** A year. The path contains a uuid, so the bytes at a URL never change. */
    private const CACHE_SECONDS = 31536000;

    public function __invoke(Request $request, PropertyPhoto $photo): Response|StreamedResponse
    {
        abort_unless($photo->isUploaded(), 404);
        abort_unless($photo->fileExists(), 404, 'The stored image is missing.');

        // The content is immutable, so a matching ETag can skip the body
        // entirely rather than streaming the file again.
        $etag = '"'.substr((string) $photo->sha256, 0, 32).'"';

        if (trim((string) $request->headers->get('If-None-Match')) === $etag) {
            return response('', 304, [
                'ETag'          => $etag,
                'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS.', immutable',
            ]);
        }

        return Storage::disk($photo->disk)->response($photo->path, null, [
            'Content-Type'  => $photo->mime_type ?: 'image/webp',
            'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS.', immutable',
            'ETag'          => $etag,
        ]);
    }
}
