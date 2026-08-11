<?php

namespace App\Http\Controllers;

use App\Models\PressRelease;
use Illuminate\View\View;

/**
 * /press — company announcements an operator has published, plus brand assets
 * and a media contact.
 *
 * Nothing is seeded. Inventing coverage, awards or partnerships would be a
 * misrepresentation, so the page ships with an empty announcements list and a
 * working media contact until there is something real to publish.
 */
class PressController extends Controller
{
    public function index(): View
    {
        return view('site.press', [
            'releases' => PressRelease::query()->live()->orderByDesc('published_at')->paginate(10),
            'mediaEmail' => setting('general.support_email', 'press@vaytoven.com'),
        ]);
    }

    public function show(PressRelease $release): View
    {
        abort_unless(
            $release->is_published && $release->published_at !== null && ! $release->published_at->isFuture(),
            404,
        );

        return view('site.press-show', [
            'release' => $release,
            'mediaEmail' => $release->media_contact_email
                ?: setting('general.support_email', 'press@vaytoven.com'),
        ]);
    }
}
