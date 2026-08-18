<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberDocument;
use App\Models\Property;
use App\Services\AdminAuditLogService;
use App\Support\Storage\DocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documents filed against one property.
 *
 * The same store as member documents, with a property attached. The storage
 * guard, the hashing, the audit trail and the missing-file handling are the
 * member-document ones deliberately — duplicating them into a parallel
 * implementation is how two copies of the same bug end up being fixed once.
 */
class PropertyDocumentController extends Controller
{
    /** 20 MB. Big enough for a scanned agreement, small enough to refuse a video. */
    private const MAX_KB = 20480;

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

        // Refused before the file is touched. Accepting an upload onto a disk
        // that loses it would show a signed agreement in the list and lose it
        // silently — the worst outcome for a record whose whole purpose is to
        // exist later during a dispute.
        if (! DocumentStorage::isDurable()) {
            return back()->withErrors(['file' => 'Uploads are disabled: '.DocumentStorage::reason()]);
        }

        $validated = $request->validate([
            'file'     => ['required', 'file', 'max:'.self::MAX_KB,
                           'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt'],
            'category' => ['required', Rule::in(array_keys(MemberDocument::CATEGORIES))],
            'title'    => ['nullable', 'string', 'max:200'],
        ]);

        $file = $request->file('file');
        $disk = DocumentStorage::disk();

        // Hashed before storing: on a remote disk, reading it back to hash
        // would be a second round trip for the same bytes.
        $sha256 = hash_file('sha256', $file->getRealPath());

        // Foldered per property, with a generated name. The uploaded filename
        // is kept as a column but never used as a path — it is
        // attacker-supplied and often contains characters a filesystem or URL
        // will not survive.
        $path = $file->store("property-documents/{$property->id}", $disk);

        $document = MemberDocument::create([
            // Filed against the property AND its owner, so it appears on the
            // member's file as well. A document about a listing is still a
            // document about the person who owns it.
            'user_id'             => $property->host_id,
            'property_id'         => $property->id,
            'category'            => $validated['category'],
            'title'               => ($validated['title'] ?? null) ?: $file->getClientOriginalName(),
            'disk'                => $disk,
            'path'                => $path,
            'original_name'       => $file->getClientOriginalName(),
            'mime_type'           => $file->getClientMimeType(),
            'size_bytes'          => $file->getSize(),
            'sha256'              => $sha256,
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'property_document.uploaded',
            subject:   $property,
            payload:   [
                'document_id' => $document->id,
                'reference'   => $property->reference,
                'filename'    => $document->original_name,
                'sha256'      => $sha256,
            ],
            ipAddress: $request->ip(),
        );

        return back()->with('success', "Uploaded \"{$document->title}\".");
    }

    public function download(Request $request, Property $property, MemberDocument $document): StreamedResponse
    {
        $this->authorizeListing($request, $property);

        // The document must belong to the property in the URL. Without this,
        // any document id could be fetched through any property's route.
        abort_unless($document->property_id === $property->id, 404);
        abort_unless($document->fileExists(), 404, 'The stored file is missing.');

        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'property_document.downloaded',
            subject:   $property,
            payload:   ['document_id' => $document->id, 'filename' => $document->original_name],
            ipAddress: $request->ip(),
        );

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function destroy(Request $request, Property $property, MemberDocument $document): RedirectResponse
    {
        $this->authorizeListing($request, $property);
        abort_unless($document->property_id === $property->id, 404);

        // Audited BEFORE deleting, so the record of what was removed survives
        // the thing it describes.
        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'property_document.deleted',
            subject:   $property,
            payload:   [
                'document_id' => $document->id,
                'reference'   => $property->reference,
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
