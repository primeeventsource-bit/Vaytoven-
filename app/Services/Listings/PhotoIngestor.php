<?php

namespace App\Services\Listings;

use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\User;
use App\Support\Storage\DocumentStorage;
use GdImage;
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
 * Rotation and cropping go through the same path: the stored numbers are
 * replayed against the pristine original, never against the last derivative.
 * Turning an image four times therefore returns it to exactly where it started,
 * and an over-tight crop is widened by dragging the box back out rather than by
 * re-uploading.
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

    /**
     * Smallest crop worth honouring, as a fraction of the edge.
     *
     * A box a few pixels wide is a misdrag, not an intention, and blowing it
     * back up to gallery size produces a blurred mess that reads as a broken
     * upload rather than as a mistake someone can undo.
     */
    private const MIN_CROP = 0.05;

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

        $bytes = (string) file_get_contents($realPath);

        Storage::disk($disk)->put($originalPath, $bytes);

        [$derivative, $servedWidth, $servedHeight] = $this->render($bytes);

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
     * Re-derive a photo with a new rotation and crop.
     *
     * The rotation is absolute, not a delta, so replaying the same request
     * twice — a double-click, a refresh of the POST — leaves the image where it
     * already was instead of turning it twice.
     *
     * The new derivative gets a fresh key rather than overwriting the old one.
     * Overwriting is tidier on the bucket and wrong everywhere else: the gallery
     * URL would be unchanged, so browsers, any cache in front of the site and
     * anyone holding an open tab would keep showing the pre-crop image with no
     * way to tell it was stale. The old object is deleted only once the new one
     * is safely written.
     *
     * @param  array{x: float, y: float, w: float, h: float}|null  $crop  fractions of the rotated image
     *
     * @throws RuntimeException when the original is gone, so nothing can be re-derived
     */
    public function retransform(PropertyPhoto $photo, int $rotation, ?array $crop): PropertyPhoto
    {
        if (! $photo->original_path || ! Storage::disk($photo->disk)->exists($photo->original_path)) {
            // Without the original there is nothing to replay against. Editing
            // the derivative instead would compound the crop already applied,
            // and would do it silently, which is worse than refusing.
            throw new RuntimeException(
                'The original upload for this photo is no longer in storage, so it cannot be rotated or cropped. Upload it again.'
            );
        }

        $bytes = (string) Storage::disk($photo->disk)->get($photo->original_path);

        [$derivative, $width, $height] = $this->render($bytes, $rotation, $crop);

        $previous   = (string) $photo->path;
        $servedPath = preg_replace('/[^\/]+$/', Str::uuid()->toString().'.webp', $previous);

        Storage::disk($photo->disk)->put($servedPath, $derivative);

        $photo->forceFill([
            'path'       => $servedPath,
            'width'      => $width,
            'height'     => $height,
            'size_bytes' => strlen($derivative),
            'sha256'     => hash('sha256', $derivative),
            'rotation'   => $this->normaliseRotation($rotation),
            'crop_x'     => $crop['x'] ?? null,
            'crop_y'     => $crop['y'] ?? null,
            'crop_w'     => $crop['w'] ?? null,
            'crop_h'     => $crop['h'] ?? null,
        ])->save();

        if ($previous !== '' && $previous !== $servedPath) {
            Storage::disk($photo->disk)->delete($previous);
        }

        return $photo;
    }

    /**
     * Turn original bytes into the image the site serves.
     *
     * Rotation is applied before the crop because that is the order the person
     * doing it experiences: they straighten the photo, then draw a box on what
     * they can now see. Storing the box against the rotated image means the
     * editor paints it straight back over the thumbnail with no arithmetic and
     * nothing to un-rotate.
     *
     * Re-encoding rather than copying is also what strips EXIF, which routinely
     * carries the GPS coordinates of the property and the photographer's device
     * — neither of which belongs on a public listing whose whole point is that
     * the member controls how precisely they are located.
     *
     * @param  array{x: float, y: float, w: float, h: float}|null  $crop
     * @return array{0: string, 1: int, 2: int}
     */
    private function render(string $bytes, int $rotation = 0, ?array $crop = null): array
    {
        $image = @imagecreatefromstring($bytes);

        if (! $image) {
            throw new RuntimeException('That image format cannot be processed. Use JPG, PNG or WebP.');
        }

        $this->keepTransparency($image);

        if ($degrees = $this->normaliseRotation($rotation)) {
            // GD turns anticlockwise for a positive angle; the button says
            // "rotate right", so the sign is flipped here rather than at every
            // call site.
            $rotated = imagerotate($image, -$degrees, imagecolorallocatealpha($image, 0, 0, 0, 127));

            if ($rotated === false) {
                throw new RuntimeException('That image could not be rotated.');
            }

            $image = $this->keepTransparency($rotated);
        }

        if ($crop) {
            $image = $this->applyCrop($image, $crop);
        }

        $width   = imagesx($image);
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
        $encoded = (string) ob_get_clean();

        return [$encoded, $width, $height];
    }

    /**
     * Cut the requested box out of the image.
     *
     * The fractions arrive from a form, so they are clamped rather than
     * trusted: a box starting past the right edge, or one taller than what is
     * left below it, is pulled back inside instead of handed to GD, which
     * answers a nonsensical rectangle with a blank canvas and no error.
     *
     * @param  array{x: float, y: float, w: float, h: float}  $crop
     */
    private function applyCrop(GdImage $image, array $crop): GdImage
    {
        $width  = imagesx($image);
        $height = imagesy($image);

        $x = min(max((float) $crop['x'], 0.0), 1.0 - self::MIN_CROP);
        $y = min(max((float) $crop['y'], 0.0), 1.0 - self::MIN_CROP);
        $w = min(max((float) $crop['w'], self::MIN_CROP), 1.0 - $x);
        $h = min(max((float) $crop['h'], self::MIN_CROP), 1.0 - $y);

        $cropped = imagecrop($image, [
            'x'      => (int) floor($x * $width),
            'y'      => (int) floor($y * $height),
            'width'  => max(1, (int) round($w * $width)),
            'height' => max(1, (int) round($h * $height)),
        ]);

        if ($cropped === false) {
            throw new RuntimeException('That crop could not be applied.');
        }

        return $this->keepTransparency($cropped);
    }

    /**
     * Stop GD flattening a PNG's transparency onto black.
     *
     * Every operation here returns a fresh canvas with blending back on and
     * alpha saving off, so this has to be re-applied after each one rather than
     * set once at the top.
     */
    private function keepTransparency(GdImage $image): GdImage
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    /** Quarter turns only, always 0-270, so 360 and -90 both land somewhere sane. */
    private function normaliseRotation(int $degrees): int
    {
        return ((int) round($degrees / 90) % 4 + 4) % 4 * 90;
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
