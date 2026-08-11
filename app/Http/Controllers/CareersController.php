<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobApplicationRequest;
use App\Models\JobApplication;
use App\Models\JobOpening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * /careers — renders whatever roles an operator has published, and says so
 * plainly when there are none.
 *
 * No openings are seeded. A fake listing wastes a real applicant's time and
 * misrepresents the company, so the empty state is the correct output until
 * someone publishes a role in the admin console.
 */
class CareersController extends Controller
{
    public function index(): View
    {
        return view('site.careers', [
            'openings' => JobOpening::query()->open()->orderBy('sort_order')->orderBy('title')->get(),
        ]);
    }

    public function show(JobOpening $opening): View
    {
        // A closed or unpublished role is not browsable, even by URL.
        abort_unless($opening->isOpen(), 404);

        return view('site.career-show', ['opening' => $opening]);
    }

    public function apply(StoreJobApplicationRequest $request, JobOpening $opening): RedirectResponse
    {
        abort_unless($opening->isOpen(), 404);

        // Résumés must land on DURABLE storage. The container filesystem on
        // Laravel Cloud is ephemeral, so writing to the `local` disk would
        // silently lose every attachment on the next deploy — the file would
        // upload fine, the record would save fine, and the document would be
        // gone. Prefer object storage whenever a bucket is configured.
        $path = $request->hasFile('resume')
            ? $request->file('resume')->store('applications/'.$opening->id, $this->resumeDisk())
            : null;

        $application = JobApplication::create([
            'job_opening_id' => $opening->id,
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'cover_note' => $request->validated('cover_note'),
            'resume_path' => $path,
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('careers.show', $opening)
            ->with('application_reference', $application->reference)
            ->with('application_success', 'Application received. We read every one and will be in touch.');
    }

    /**
     * Where a résumé is written. Three things must hold, and none of them can
     * be left to a comment:
     *
     *   1. NEVER the `public` disk. That disk is symlinked to public/storage
     *      with public visibility, so a candidate's CV would be downloadable
     *      by anyone who guessed the path.
     *   2. Prefer object storage, and only when it is genuinely configured —
     *      testing a disk name that has no driver throws on every submission.
     *   3. `local` is the last resort and is ephemeral on Laravel Cloud, so it
     *      is logged loudly rather than failing silently at the next deploy.
     */
    private function resumeDisk(): string
    {
        $configured = config('filesystems.default');

        foreach (['s3', 'private'] as $preferred) {
            if (config("filesystems.disks.{$preferred}.driver")) {
                return $preferred;
            }
        }

        if ($configured === 'public') {
            Log::error('careers: FILESYSTEM_DISK is "public"; refusing to write résumés to a world-readable disk.');

            return 'local';
        }

        if ($configured === 'local') {
            Log::warning('careers: résumé stored on the ephemeral local disk. Attachments will be lost on the next deploy — configure S3.');
        }

        return $configured;
    }
}
