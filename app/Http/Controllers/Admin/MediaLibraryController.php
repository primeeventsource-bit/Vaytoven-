<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\MediaCollection;
use App\Services\AdminAuditLogService;
use App\Services\Listings\PhotoIngestor;
use App\Support\Storage\DocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The shared photo library.
 *
 * Exists so the stock photography staff keep on hand is uploaded once and
 * reused, instead of being re-uploaded per listing. Everything here is
 * internal: nothing in this controller is reachable by a member or a visitor.
 */
class MediaLibraryController extends Controller
{
    /** 15 MB per image, matching the listing uploader. */
    private const MAX_KB = 15360;

    public function __construct(private readonly PhotoIngestor $ingestor)
    {
    }

    public function index(Request $request): View
    {
        $collections = MediaCollection::withCount('assets')->orderBy('name')->get();

        $current = $request->filled('collection')
            ? MediaCollection::where('slug', $request->query('collection'))->first()
            : null;

        $assets = MediaAsset::query()
            ->with('uploadedBy:id,email')
            ->when($current, fn ($q) => $q->where('media_collection_id', $current->id))
            // "Unsorted" is a real place, not an absence: an upload nobody
            // filed still has to be findable rather than invisible.
            ->when($request->query('collection') === 'unsorted', fn ($q) => $q->whereNull('media_collection_id'))
            ->latest('id')
            ->paginate(48)
            ->withQueryString();

        return view('admin.media.index', [
            'collections'    => $collections,
            'current'        => $current,
            'currentSlug'    => (string) $request->query('collection', ''),
            'assets'         => $assets,
            'unsortedCount'  => MediaAsset::whereNull('media_collection_id')->count(),
            'storageDurable' => DocumentStorage::isDurable(),
            'storageReason'  => DocumentStorage::reason(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! DocumentStorage::isDurable()) {
            return back()->withErrors(['assets' => 'Photo uploads are disabled: '.DocumentStorage::reason()]);
        }

        $validated = $request->validate([
            'assets'     => ['required', 'array', 'max:60'],
            'assets.*'   => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KB],
            'collection' => ['nullable', 'exists:media_collections,id'],
        ], [
            'assets.*.mimes' => 'Only JPG, PNG and WebP images can be uploaded.',
            'assets.max'     => 'Sixty images at a time is the limit; add the rest in a second batch.',
        ]);

        $collection = isset($validated['collection'])
            ? MediaCollection::find($validated['collection'])
            : null;

        $stored   = 0;
        $failures = [];

        foreach ($request->file('assets') as $file) {
            try {
                $this->ingestor->ingestAsset($file, $request->user(), $collection);
                $stored++;
            } catch (Throwable $e) {
                // One bad file must not discard the rest of the batch, and the
                // person needs to know which one failed by name.
                $failures[] = $file->getClientOriginalName().': '.$e->getMessage();
            }
        }

        if ($stored > 0) {
            AdminAuditLogService::log(
                actor:     $request->user(),
                action:    'media.uploaded',
                subject:   $collection,
                payload:   ['count' => $stored, 'collection' => $collection?->name ?? 'Unsorted'],
                ipAddress: $request->ip(),
            );
        }

        if ($failures) {
            return back()
                ->with('success', $stored ? "{$stored} image(s) added to the library." : null)
                ->withErrors(['assets' => implode(' · ', $failures)]);
        }

        return back()->with('success', "{$stored} image(s) added to the library.");
    }

    /** Rename, re-file, or describe a single asset. */
    public function update(Request $request, MediaAsset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'label'      => ['nullable', 'string', 'max:255'],
            'alt_text'   => ['nullable', 'string', 'max:255'],
            'collection' => ['nullable', 'exists:media_collections,id'],
        ]);

        $asset->update([
            'label'               => $validated['label'] ?? null,
            'alt_text'            => $validated['alt_text'] ?? null,
            'media_collection_id' => $validated['collection'] ?? null,
        ]);

        return back()->with('success', 'Image updated.');
    }

    public function destroy(Request $request, MediaAsset $asset): RedirectResponse
    {
        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'media.deleted',
            subject:   $asset->collection,
            payload:   ['asset_id' => $asset->id, 'filename' => $asset->original_name, 'sha256' => $asset->sha256],
            ipAddress: $request->ip(),
        );

        // Only the library's own objects are removed. Listings that used this
        // image hold their own copies and are untouched by design — that is the
        // whole reason attaching copies rather than referencing.
        Storage::disk($asset->disk)->delete(array_filter([$asset->path, $asset->original_path]));

        $asset->delete();

        return back()->with('success', 'Image removed from the library.');
    }

    /**
     * Stream a library image.
     *
     * The bucket is private, so images are served through the app. Behind the
     * admin permission gate: stock photography is internal until it is put on
     * a listing.
     */
    public function show(MediaAsset $asset): StreamedResponse
    {
        abort_unless($asset->fileExists(), 404);

        return Storage::disk($asset->disk)->response(
            $asset->path,
            null,
            [
                'Content-Type'  => 'image/webp',
                // Private: an admin image must not be kept by a shared cache.
                'Cache-Control' => 'private, max-age=3600',
            ],
        );
    }
}
