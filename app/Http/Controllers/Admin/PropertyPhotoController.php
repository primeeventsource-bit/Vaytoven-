<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Services\AdminAuditLogService;
use App\Services\Listings\PhotoIngestor;
use App\Support\Storage\DocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class PropertyPhotoController extends Controller
{
    /** 15 MB per image. A phone photo is 2-5 MB; anything past this is a raw file. */
    private const MAX_KB = 15360;

    public function __construct(private readonly PhotoIngestor $ingestor)
    {
    }

    /**
     * The same rule the listing builder uses: staff may touch anything,
     * everyone else only their own property. properties.edit alone is not
     * enough, because the RBAC host role grants it.
     */
    private function authorizeListing(Request $request, Property $property): void
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->isStaff() || $property->host_id === $user->id),
            403,
            'This listing belongs to another account.',
        );
    }

    public function store(Request $request, Property $property): RedirectResponse
    {
        $this->authorizeListing($request, $property);

        if (! DocumentStorage::isDurable()) {
            return back()->withErrors(['photos' => 'Photo uploads are disabled: '.DocumentStorage::reason()]);
        }

        $validated = $request->validate([
            'photos'   => ['required', 'array', 'max:30'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KB],
            'category' => ['nullable', Rule::in(array_keys(PropertyPhoto::CATEGORIES))],
        ], [
            'photos.*.mimes' => 'Only JPG, PNG and WebP images can be uploaded.',
            'photos.max'     => 'Thirty images at a time is the limit; add the rest in a second batch.',
        ]);

        $category = $validated['category'] ?? 'other';
        $stored   = 0;
        $failures = [];

        foreach ($request->file('photos') as $file) {
            try {
                $photo = $this->ingestor->ingest($property, $file, $request->user(), $category);
                $stored++;

                AdminAuditLogService::log(
                    actor:     $request->user(),
                    action:    'property_photo.uploaded',
                    subject:   $property,
                    payload:   [
                        'photo_id' => $photo->id,
                        'filename' => $photo->original_name,
                        'sha256'   => $photo->sha256,
                    ],
                    ipAddress: $request->ip(),
                );
            } catch (Throwable $e) {
                // One bad file in a batch of twenty must not discard the
                // nineteen that were fine, and the person needs to know which
                // one failed rather than being told "upload failed".
                $failures[] = $file->getClientOriginalName().': '.$e->getMessage();
            }
        }

        if ($stored > 0 && ! PropertyPhoto::where('property_id', $property->id)->where('is_cover', true)->exists()) {
            // A gallery with no cover leaves the search card picking arbitrarily.
            PropertyPhoto::where('property_id', $property->id)->orderBy('sort_order')->first()?->makeCover();
        }

        if ($failures) {
            return back()
                ->with('success', $stored ? "{$stored} photo(s) uploaded." : null)
                ->withErrors(['photos' => implode(' · ', $failures)]);
        }

        if ($stored > 0) {
            app(\App\Services\Tracking\ActivityRecorder::class)->record(
                \App\Enums\ActivityType::ImagesUploaded,
                $request,
                subjectType: 'property',
                subjectReference: $property->reference,
                result: 'completed',
                metadata: ['count' => $stored],
            );
        }

        return back()->with('success', "{$stored} photo(s) uploaded.");
    }

    public function update(Request $request, Property $property, PropertyPhoto $photo): RedirectResponse
    {
        $this->authorizeListing($request, $property);
        abort_unless($photo->property_id === $property->id, 404);

        $photo->update($request->validate([
            'caption'  => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(PropertyPhoto::CATEGORIES))],
        ]));

        return back()->with('success', 'Photo details saved.');
    }

    /**
     * Rotate and crop.
     *
     * Both arrive together because they are one edit as far as the person is
     * concerned, and because the crop box is expressed against the rotated
     * image — sending them separately would mean a box drawn before a turn
     * lands somewhere else after it.
     *
     * The rotation posted is absolute. The buttons do the addition client-side
     * and send where the image should end up, so a double-submitted form is a
     * no-op rather than a second quarter turn.
     */
    public function transform(Request $request, Property $property, PropertyPhoto $photo): RedirectResponse
    {
        $this->authorizeListing($request, $property);
        abort_unless($photo->property_id === $property->id, 404);

        $validated = $request->validate([
            'rotation' => ['required', 'integer', Rule::in([0, 90, 180, 270])],
            // All four or none: a partial box cannot be drawn, and guessing the
            // missing side would crop somewhere the person never chose.
            'crop_x'   => ['nullable', 'required_with:crop_w', 'numeric', 'between:0,1'],
            'crop_y'   => ['nullable', 'required_with:crop_h', 'numeric', 'between:0,1'],
            'crop_w'   => ['nullable', 'required_with:crop_x', 'numeric', 'between:0.05,1'],
            'crop_h'   => ['nullable', 'required_with:crop_y', 'numeric', 'between:0.05,1'],
        ], [
            'crop_w.between' => 'That crop is too small — drag a larger area.',
            'crop_h.between' => 'That crop is too small — drag a larger area.',
        ]);

        $crop = isset($validated['crop_w'], $validated['crop_h'])
            ? [
                'x' => (float) ($validated['crop_x'] ?? 0),
                'y' => (float) ($validated['crop_y'] ?? 0),
                'w' => (float) $validated['crop_w'],
                'h' => (float) $validated['crop_h'],
            ]
            : null;

        $before = ['rotation' => (int) $photo->rotation, 'crop' => $photo->cropBox()];

        try {
            $this->ingestor->retransform($photo, (int) $validated['rotation'], $crop);
        } catch (Throwable $e) {
            return back()->withErrors(['photo_transform' => $e->getMessage()]);
        }

        // Logged because the served image changed while the listing was live:
        // a dispute about what an advertisement showed needs the edit, not just
        // the upload that preceded it.
        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'property_photo.transformed',
            subject:   $property,
            payload:   [
                'photo_id' => $photo->id,
                'from'     => $before,
                'to'       => ['rotation' => (int) $photo->rotation, 'crop' => $photo->cropBox()],
                'sha256'   => $photo->sha256,
            ],
            ipAddress: $request->ip(),
        );

        return back()->with('success', $photo->isEdited() ? 'Photo updated.' : 'Photo reset to the original.');
    }

    public function cover(Request $request, Property $property, PropertyPhoto $photo): RedirectResponse
    {
        $this->authorizeListing($request, $property);
        abort_unless($photo->property_id === $property->id, 404);

        $photo->makeCover();

        return back()->with('success', 'Cover photo set.');
    }

    /**
     * Persist a drag-and-drop reorder.
     *
     * Only ids belonging to this property are honoured; a posted id from
     * another listing is ignored rather than silently reordering it.
     */
    public function reorder(Request $request, Property $property): RedirectResponse
    {
        $this->authorizeListing($request, $property);

        $validated = $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $owned = PropertyPhoto::where('property_id', $property->id)->pluck('id')->all();

        foreach (array_values($validated['order']) as $position => $id) {
            if (! in_array((int) $id, $owned, true)) {
                continue;
            }

            PropertyPhoto::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return back()->with('success', 'Photo order saved.');
    }

    public function destroy(Request $request, Property $property, PropertyPhoto $photo): RedirectResponse
    {
        $this->authorizeListing($request, $property);
        abort_unless($photo->property_id === $property->id, 404);

        // Audited before deletion, so the record of what was removed survives
        // the thing it describes.
        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'property_photo.deleted',
            subject:   $property,
            payload:   [
                'photo_id' => $photo->id,
                'filename' => $photo->original_name,
                'sha256'   => $photo->sha256,
            ],
            ipAddress: $request->ip(),
        );

        $wasCover = $photo->is_cover;

        if ($photo->isUploaded()) {
            Storage::disk($photo->disk)->delete(array_filter([$photo->path, $photo->original_path]));
        }

        $photo->delete();

        if ($wasCover) {
            PropertyPhoto::where('property_id', $property->id)->orderBy('sort_order')->first()?->makeCover();
        }

        return back()->with('success', 'Photo deleted.');
    }
}
