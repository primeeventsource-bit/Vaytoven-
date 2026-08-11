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
        $disk = config('filesystems.disks.s3.bucket') ? 's3' : config('filesystems.default');

        if ($disk === 'local') {
            Log::warning('careers: résumé stored on the ephemeral local disk; set FILESYSTEM_DISK to durable storage.', [
                'opening_id' => $opening->id,
            ]);
        }

        // Never the `public` disk — these are candidates' personal documents.
        $path = $request->file('resume')?->store('applications/'.$opening->id, $disk);

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
}
