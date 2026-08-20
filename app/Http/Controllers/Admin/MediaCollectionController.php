<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Folders in the shared photo library.
 *
 * Thin on purpose — a collection is a name and a slug. The slug is derived
 * from the name and never edited afterwards, because it is part of the storage
 * key for every asset filed under it and renaming it would orphan them.
 */
class MediaCollectionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $collection = MediaCollection::create([
            'name'               => $validated['name'],
            'description'        => $validated['description'] ?? null,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.media.index', ['collection' => $collection->slug])
            ->with('success', 'Folder created.');
    }

    /**
     * The display name only. The slug stays as first generated because assets
     * are stored under it; changing it would leave their files unreachable.
     */
    public function update(Request $request, MediaCollection $collection): RedirectResponse
    {
        $collection->update($request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ]));

        return back()->with('success', 'Folder renamed.');
    }

    /**
     * Delete the folder, keep the pictures.
     *
     * The assets fall back to Unsorted rather than being destroyed — deleting a
     * folder is a filing decision, and losing a stock library to one is the
     * kind of mistake nobody recovers from.
     */
    public function destroy(MediaCollection $collection): RedirectResponse
    {
        $moved = $collection->assets()->count();

        $collection->assets()->update(['media_collection_id' => null]);
        $collection->delete();

        return redirect()
            ->route('admin.media.index')
            ->with('success', $moved
                ? "Folder deleted. {$moved} image(s) moved to Unsorted."
                : 'Folder deleted.');
    }
}
