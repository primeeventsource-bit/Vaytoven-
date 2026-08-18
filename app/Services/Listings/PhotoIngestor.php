<?php

namespace App\Services\Listings;

use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\User;
use App\Support\Storage\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Takes an uploaded image and produces what the site serves.
 *
 * Two copies are kept. The original is stored untouched, because re-deriving
 * from a compressed copy loses more each time and because "what did the
 * advertisement actually show" has to be answerable years later. The derivative
 * is what visitors get: bounded, stripped of metadata, and usually a fraction
 * of the size.
 *
 * Resizing happens inline rather than on a queue. That is not the textbook
 * answer, but the environment serving the public site runs QUEUE_CONNECTION=sync
 * with no worker attached, so a "queued" job would execute inside the request
 * anyway — with the added failure mode that a genuinely queued one would sit
 * unprocessed forever and the gallery would show nothing. Inline and honest
 * beats queued and invisible. MAX_PIXELS caps the work per image so a camera
 * raw cannot exhaust the request.
 */
class PhotoIngestor
{
    /** Longest edge of the served image. Larger than any layout slot, and cheap. */
    private const MAX_EDGE = 2400;

    /**
     * Refuse to decode anything bigger than this many pixels.
     *
     * A decompression bomb is a small file that expands to gigabytes in memory:
     * a 64,000 x 64,000 PNG is a few hundred KB on disk and about 12GB decoded.
     * The size limit in validation cannot see that, because it measures the
     * file, not what it becomes.
     */
    private const MAX_PIXELS = 50_000_000;

    private const QUALITY = 82;

    public function __construct(private readonly ?string $environmentPrefix = null)
    {
    }

    /**
     * @throws RuntimeException when storage would lose the file, or the image
     *                          cannot be safely decoded
     */
    public function ingest(Property $property, UploadedFile $file, ?User $actor = null, string $category = 'other'): PropertyPhoto
    {
        if (! DocumentStorage::isDurable()) {
            throw new RuntimeException(
                'Photo uploads are disabled: '.DocumentStorage::reason()
            );
        }

        $realPath = $file->getRealPath();
        $info     = @getimagesize($realPath);

        if ($info === false) {
            throw new RuntimeException('That file is not an image the server can read.');
        }

        [$width, $height] = $info;

        if (($width * $height) > self::MAX_PIXELS) {
            throw new RuntimeException(
                'That image is too large to process ('.$width.'x'.$height.' pixels).'
            );
        }

        $disk   = DocumentStorage::disk();
        $prefix = $this->prefix();
        $stem   = Str::uuid()->toString();

        // The uploaded filename is attacker-supplied and is kept as data only.
        $originalPath = "{$prefix}/properties/{$property->id}/originals/{$stem}.".
            strtolower($file->getClientOriginalExtension() ?: 'bin');

        Storage::disk($disk)->put($originalPath, file_get_contents($realPath));

        [$derivative, $servedWidth, $servedHeight] = $this->derive($realPath, $info[2]);

        $servedPath = "{$prefix}/properties/{$property->id}/web/{$stem}.webp";
        Storage::disk($disk)->put($servedPath, $derivative);

        return PropertyPhoto::create([
            'property_id'         => $property->id,
            'disk'                => $disk,
            'path'                => $servedPath,
            'original_path'       => $originalPath,
            'category'            => $category,
            'original_name'       => $file->getClientOriginalName(),
            'mime_type'           => 'image/webp',
            'size_bytes'          => strlen($derivative),
            'width'               => $servedWidth,
            'height'              => $servedHeight,
            'sha256'              => hash('sha256', $derivative),
            'uploaded_by_user_id' => $actor?->id,
            'sort_order'          => (int) PropertyPhoto::where('property_id', $property->id)->max('sort_order') + 1,
        ]);
    }

    /**
     * Scale down and re-encode as WebP.
     *
     * Re-encoding rather than copying is also what strips EXIF, which routinely
     * carries the GPS coordinates of the property and the photographer's device
     * — neither of which belongs on a public listing whose whole point is that
     * the member controls how precisely they are located.
     *
     * @return array{0: string, 1: int, 2: int}
     */
    private function derive(string $path, int $type): array
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default        => null,
        };

        if (! $image) {
            throw new RuntimeException('That image format cannot be processed. Use JPG, PNG or WebP.');
        }

        $width  = imagesx($image);
        $height  = imagesy($image);
        $longest = max($width, $height);

        if ($longest > self::MAX_EDGE) {
            $scale     = self::MAX_EDGE / $longest;
            $newWidth  = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));

            $resized = imagescale($image, $newWidth, $newHeight);

            if ($resized === false) {
                throw new RuntimeException('That image could not be resized.');
            }

            // No imagedestroy(): a no-op since PHP 8.0 and deprecated in 8.5,
            // which the server runs. It wrote three deprecation lines per
            // upload and freed nothing.
            $image  = $resized;
            $width  = $newWidth;
            $height = $newHeight;
        }

        ob_start();
        imagewebp($image, null, self::QUALITY);
        $bytes = (string) ob_get_clean();

        return [$bytes, $width, $height];
    }

    /**
     * Keys are namespaced per environment.
     *
     * main and production are separate databases numbering properties
     * independently, so a bare properties/{id}/ key would have main's property
     * 42 and production's property 42 writing over each other the moment both
     * point at one bucket.
     */
    private function prefix(): string
    {
        if ($this->environmentPrefix) {
            return $this->environmentPrefix;
        }

        // NOT app()->environment(). Every Laravel Cloud environment on this
        // app runs APP_ENV=production - main included - so environment() gives
        // the same answer everywhere and the namespacing it was supposed to
        // provide would be imaginary. A live probe caught that: photos uploaded
        // from main were landing under "production/".
        //
        // The host is the one value that genuinely differs per environment.
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! $host) {
            return app()->environment();
        }

        return preg_replace('/[^a-z0-9]+/', '-', strtolower($host)) ?: app()->environment();
    }
}
