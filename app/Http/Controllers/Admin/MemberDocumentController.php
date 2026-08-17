<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberDocument;
use App\Models\User;
use App\Services\AdminAuditLogService;
use App\Support\Storage\DocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberDocumentController extends Controller
{
    /** 20 MB. Big enough for a scanned agreement, small enough to refuse a video. */
    private const MAX_KB = 20480;

    public function store(Request $request, User $user): RedirectResponse
    {
        // Refuse before touching the file. Accepting an upload onto a disk
        // that loses it on the next deploy would show a signed agreement in
        // the list and lose it silently — the worst outcome for a record whose
        // whole purpose is to exist later.
        if (! DocumentStorage::isDurable()) {
            return back()->withErrors([
                'file' => 'Uploads are disabled: this environment has no durable storage attached, '
                    .'so files would be lost on the next deploy. Attach a Cloud disk and set '
                    .'FILESYSTEM_DISK before using this.',
            ]);
        }

        $validated = $request->validate([
            'file'     => ['required', 'file', 'max:'.self::MAX_KB,
                           'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt'],
            'category' => ['required', Rule::in(array_keys(MemberDocument::CATEGORIES))],
            'title'    => ['nullable', 'string', 'max:200'],
            'notes'    => ['nullable', 'string', 'max:2000'],
        ]);

        $file = $request->file('file');
        $disk = DocumentStorage::disk();

        // Hash before storing: on a remote disk, reading it back to hash would
        // be a second round trip for the same bytes.
        $sha256 = hash_file('sha256', $file->getRealPath());

        // Foldered per member, with a generated name. The original filename is
        // kept as a column but never used as a path — it is attacker-supplied
        // and often contains characters a filesystem or URL will not survive.
        $path = $file->store("member-documents/{$user->id}", $disk);

        $document = MemberDocument::create([
            'user_id'             => $user->id,
            'category'            => $validated['category'],
            // A nullable field that was never submitted is absent from the
            // validated data, not null — so ?? comes before the ?: fallback.
            'title'               => ($validated['title'] ?? null) ?: $file->getClientOriginalName(),
            'disk'                => $disk,
            'path'                => $path,
            'original_name'       => $file->getClientOriginalName(),
            'mime_type'           => $file->getClientMimeType(),
            'size_bytes'          => $file->getSize(),
            'sha256'              => $sha256,
            'uploaded_by_user_id' => $request->user()->id,
            'notes'               => $validated['notes'] ?? null,
        ]);

        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'member_document.uploaded',
            subject:   $user,
            payload:   [
                'document_id' => $document->id,
                'category'    => $document->category,
                'filename'    => $document->original_name,
                'sha256'      => $sha256,
            ],
            ipAddress: $request->ip(),
        );

        return back()->with('success', "Uploaded \"{$document->title}\".");
    }

    public function download(Request $request, User $user, MemberDocument $document): StreamedResponse
    {
        // The document must belong to the member in the URL. Without this,
        // any document id could be fetched through any member's route.
        abort_unless($document->user_id === $user->id, 404);
        abort_unless($document->fileExists(), 404, 'The stored file is missing.');

        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'member_document.downloaded',
            subject:   $user,
            payload:   ['document_id' => $document->id, 'filename' => $document->original_name],
            ipAddress: $request->ip(),
        );

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function destroy(Request $request, User $user, MemberDocument $document): RedirectResponse
    {
        abort_unless($document->user_id === $user->id, 404);

        // Audit BEFORE deleting, so the record of what was removed survives
        // the thing it describes.
        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'member_document.deleted',
            subject:   $user,
            payload:   [
                'document_id' => $document->id,
                'category'    => $document->category,
                'filename'    => $document->original_name,
                'sha256'      => $document->sha256,
            ],
            ipAddress: $request->ip(),
        );

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        return back()->with('success', 'Document deleted.');
    }
}
