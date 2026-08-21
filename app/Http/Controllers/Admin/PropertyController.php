<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminPropertyRequest;
use App\Http\Requests\Admin\UpdateListingRequest;
use App\Support\Listings\ListingReadiness;
use Illuminate\Validation\Rule;
use App\Models\Amenity;
use App\Mail\ListingCreatedForOwner;
use App\Enums\ActivityType;
use App\Models\MemberOffer;
use App\Models\Property;
use App\Models\PropertySnapshot;
use App\Models\PropertyView;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\AdminAuditLogService;
use App\Support\Mail\MailDeliverability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Staff-created listings.
 *
 * A super admin or admin can build a listing for an existing account or for
 * somebody who has none yet — the owner is on the phone describing their
 * property, and this is how it gets onto the platform without asking them to
 * do the typing.
 *
 * When the owner is new, the account is created alongside the listing with a
 * one-time password and must_change_password set, so the credential staff
 * issued stops working the moment the owner signs in. The owner is emailed
 * either way: a listing appearing under someone's name without telling them is
 * how a platform ends up advertising a property nobody agreed to advertise.
 */
class PropertyController extends Controller
{
    public function index(Request $request): View
    {
        $properties = Property::query()
            ->with('host:id,name,email')
            ->when($request->query('q'), function ($q, $term) {
                $q->where(function ($w) use ($term) {
                    $w->where('title', 'like', "%{$term}%")
                      ->orWhere('city', 'like', "%{$term}%")
                      ->orWhereHas('host', fn ($h) => $h->where('email', 'like', "%{$term}%"));
                });
            })
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.properties.index', [
            'properties'   => $properties,
            'statuses'     => PropertyStatus::cases(),
            'activeStatus' => $request->query('status'),
        ]);
    }

    public function create(): View
    {
        return view('admin.properties.create', [
            'owners'   => User::query()
                ->whereNull('deactivated_at')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'statuses' => PropertyStatus::cases(),
        ]);
    }

    public function store(StoreAdminPropertyRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $data  = $request->validated();

        $temporaryPassword = null;

        // One transaction: a listing owned by an account that failed to save,
        // or an account with no listing, are both worse than neither.
        [$property, $owner] = DB::transaction(function () use ($data, $actor, &$temporaryPassword) {
            if ($data['owner_mode'] === 'new') {
                [$owner, $temporaryPassword] = $this->createOwner($data, $actor);
            } else {
                $owner = User::findOrFail($data['host_id']);
            }

            $property = Property::create([
                'host_id'            => $owner->id,
                'listing_source'     => $data['listing_source'],
                'title'              => $data['title'],
                'description'        => $data['description'] ?? null,
                'address_line'       => $data['address_line'] ?? null,
                'city'               => $data['city'] ?? null,
                'region'             => $data['region'] ?? null,
                'country'            => isset($data['country']) ? strtoupper($data['country']) : null,
                'postal_code'        => $data['postal_code'] ?? null,
                'capacity'           => $data['capacity'],
                'bedrooms'           => $data['bedrooms'],
                'beds'               => $data['beds'],
                'bathrooms'          => $data['bathrooms'],
                // Dollars in, integer cents stored. round() before cast, or
                // 149.99 lands as 14998 through float truncation.
                'price_cents'  => (int) round(((float) $data['price_dollars']) * 100),
                'listing_type' => $data['listing_type'],
                'minimum_nights'     => $data['minimum_nights'] ?? 1,
                'status'             => $data['status'],
            ]);

            return [$property, $owner];
        });

        AdminAuditLogService::log(
            actor:     $actor,
            action:    'property.create',
            subject:   $property,
            payload:   [
                'title'          => $property->title,
                'owner_email'    => $owner->email,
                'owner_created'  => $data['owner_mode'] === 'new',
                'status'         => $property->status->value ?? (string) $property->status,
            ],
            ipAddress: $request->ip(),
        );

        $emailed = false;
        if ($data['notify_owner'] ?? true) {
            $emailed = $this->notifyOwner($property, $owner, $temporaryPassword);
        }

        $message = "Listing \"{$property->title}\" created for {$owner->email}.";
        $message .= $emailed
            ? ' They have been emailed.'
            : ' They could NOT be emailed — see below.';

        return redirect()
            ->route('admin.properties.index')
            ->with('success', $message)
            ->with('owner_credentials', $temporaryPassword ? [
                'email'    => $owner->email,
                'password' => $temporaryPassword,
                'emailed'  => $emailed,
            ] : null);
    }

    /**
     * @return array{0: User, 1: string} the owner and their one-time password
     */
    private function createOwner(array $data, User $actor): array
    {
        $existing = User::query()->where('email', strtolower($data['owner_email']))->first();

        // An email that already has an account is not an error — it is the
        // common case of staff not realising. Use it rather than colliding.
        if ($existing) {
            return [$existing, ''];
        }

        $password = Str::password(14, symbols: false);

        $owner = User::create([
            'name'                 => trim($data['owner_first_name'].' '.$data['owner_last_name']),
            'first_name'           => $data['owner_first_name'],
            'last_name'            => $data['owner_last_name'],
            'email'                => strtolower($data['owner_email']),
            'phone'                => $data['owner_phone'] ?? null,
            'password'             => $password,      // hashed by cast
            'role'                 => UserRole::Host,
            'must_change_password' => true,
            'created_by_user_id'   => $actor->id,
            'email_verified_at'    => now(),
        ]);

        return [$owner, $password];
    }

    private function notifyOwner(Property $property, User $owner, ?string $temporaryPassword): bool
    {
        if (! MailDeliverability::isDeliverable()) {
            Log::warning('Listing created but owner not emailed — mail is not deliverable.', [
                'property_id' => $property->id,
                'owner'       => $owner->email,
                'reason'      => MailDeliverability::reason(),
            ]);

            return false;
        }

        try {
            Mail::to($owner->email)->send(
                new ListingCreatedForOwner($property, $owner, $temporaryPassword ?: null),
            );

            return true;
        } catch (Throwable $e) {
            // The listing exists and the audit entry is written. A failed
            // email must not undo either.
            Log::error('Listing owner notification failed.', [
                'property_id' => $property->id,
                'error'       => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * The listing builder.
     *
     * One screen rather than a wizard. Staff assemble a listing out of order —
     * basics today, dates when the club confirms, photos when the member sends
     * them — and a wizard forces a sequence that does not match how the work
     * actually arrives.
     */
    /**
     * May this person build THIS listing?
 *
     * The properties.edit permission is not enough on its own: the RBAC `host`
     * role grants it so hosts can maintain their own listings, which means the
     * route middleware alone would let any host open any member's property.
     * Staff may edit anything; everyone else may edit only what they own.
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

    public function edit(Request $request, Property $property): View
    {
        $this->authorizeListing($request, $property);

        $property->load(['amenities', 'host', 'availabilityWeeks', 'memberServiceOrder', 'photos.uploadedBy']);

        return view('admin.properties.edit', [
            'property'  => $property,
            'amenities' => Amenity::query()->orderBy('category')->orderBy('label')->get()->groupBy('category'),
            'kinds'     => UpdateListingRequest::KINDS,
            'viewTypes' => UpdateListingRequest::VIEW_TYPES,
            'precision' => UpdateListingRequest::LOCATION_PRECISION,
            'statuses'  => PropertyStatus::cases(),
            'blockers'  => ListingReadiness::blockers($property),
            'storageDurable' => \App\Support\Storage\DocumentStorage::isDurable(),
            // The shared library, for the "add from photo library" picker.
            //
            // Filterable by folder. It used to be the newest 120 with no way to
            // narrow, which is fine with a handful of images and useless once
            // the library holds a couple of hundred across a dozen folders —
            // the ones you actually wanted for this listing were simply not on
            // the page, and the picker looked broken rather than truncated.
            'libraryFolders' => \App\Models\MediaCollection::withCount('assets')
                ->orderBy('name')->get(),
            'libraryFolder'  => (string) $request->query('library_folder', ''),
            'libraryAssets'  => \App\Models\MediaAsset::query()
                ->with('collection:id,name')
                ->when(
                    $request->query('library_folder') === 'unsorted',
                    fn ($q) => $q->whereNull('media_collection_id'),
                )
                ->when(
                    $request->filled('library_folder') && $request->query('library_folder') !== 'unsorted',
                    fn ($q) => $q->whereHas(
                        'collection',
                        fn ($c) => $c->where('slug', $request->query('library_folder')),
                    ),
                )
                ->latest('id')
                // Still capped, but a folder is a small enough set that the cap
                // is now a safety net rather than the thing hiding your photos.
                ->limit(300)
                ->get(),
            'libraryTotal'   => \App\Models\MediaAsset::count(),
        ]);
    }

    public function update(UpdateListingRequest $request, Property $property): RedirectResponse
    {
        $this->authorizeListing($request, $property);

        $data = $request->validated();

        // Money as integer cents, and rounded before the cast. 249.99 * 100 is
        // 24998.999... in binary floating point, which truncates to 24998.
        $data['minimum_offer_cents'] = isset($data['minimum_offer_dollars'])
            && $data['minimum_offer_dollars'] !== null
                ? (int) round(((float) $data['minimum_offer_dollars']) * 100)
                : null;
        unset($data['minimum_offer_dollars']);

        // Same treatment for the listing price. Left absent rather than
        // zeroed when the field is blank, so saving another section of the
        // builder cannot wipe a price nobody touched.
        if (array_key_exists('price_dollars', $data)) {
            if ($data['price_dollars'] !== null && $data['price_dollars'] !== '') {
                $data['price_cents'] = (int) round(((float) $data['price_dollars']) * 100);
            }

            unset($data['price_dollars']);
        }

        // Empty rows are how a repeating field reports "nothing here"; storing
        // them would print blank bullets on the listing.
        $data['highlights'] = collect($data['highlights'] ?? [])
            ->map(fn ($h) => trim((string) $h))
            ->filter()
            ->values()
            ->all();

        // Status is owned by transition(), which checks readiness. Leaving
        // it here would let a stale form field quietly republish a paused
        // listing on the next save.
        unset($data['status']);

        $amenityIds = $data['amenities'] ?? [];
        $custom     = trim((string) ($data['custom_amenity'] ?? ''));
        unset($data['amenities'], $data['custom_amenity']);

        $changed = $this->materialChanges($property, $data);

        DB::transaction(function () use ($property, $data, $amenityIds, $custom) {
            $property->update($data);

            if ($custom !== '') {
                // firstOrCreate, not create: two staff adding "Pickleball" on
                // different listings must land on one amenity, or the filter
                // ends up with duplicates that each match half the listings.
                $amenity = Amenity::firstOrCreate(
                    ['slug' => Str::slug($custom)],
                    // 'other', not 'custom'. The category column is a constrained
                    // enum; 'custom' is not one of its values, so the insert fails
                    // at the database rather than in validation. A test caught it.
                    ['label' => $custom, 'category' => 'other'],
                );
                $amenityIds[] = $amenity->id;
            }

            $property->amenities()->sync(array_unique($amenityIds));
        });

        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'property.updated',
            subject:   $property,
            payload:   ['reference' => $property->reference, 'changed' => $changed],
            ipAddress: $request->ip(),
        );

        app(\App\Services\Tracking\ActivityRecorder::class)->record(
            \App\Enums\ActivityType::PropertyEdited,
            $request,
            subjectType: 'property',
            subjectReference: $property->reference,
            result: 'completed',
            metadata: ['changed' => $changed],
        );

        return redirect()->route('admin.properties.edit', $property)
            ->with('success', 'Listing saved.');
    }

    /**
     * Which fields actually changed, for the audit entry.
     *
     * "Staff edited this listing" is not evidence of anything. During a dispute
     * the question is what was different and when, so the log records the field
     * names rather than the fact that a form was submitted. Values are left out
     * deliberately: the full before-and-after belongs in the listing snapshot,
     * which is content-hashed, not in a log line anyone could paraphrase.
     *
     * @return array<int, string>
     */
    private function materialChanges(Property $property, array $data): array
    {
        $changed = [];

        foreach ($data as $field => $value) {
            if (! array_key_exists($field, $property->getAttributes())) {
                continue;
            }

            if ((string) json_encode($property->{$field}) !== (string) json_encode($value)) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /**
     * The property hub.
     *
     * Everything about one listing in one place: who it belongs to, what it is
     * doing, and the way through to each part of it. These numbers and records
     * already existed and were scattered across four screens, which meant
     * answering "how is this advertisement doing" required knowing where to
     * look four times.
     */
    public function show(Request $request, Property $property): View
    {
        $this->authorizeListing($request, $property);

        $property->load(['host', 'memberServiceOrder', 'photos', 'availabilityWeeks']);

        // Counted here rather than carried on the property row. A cached
        // counter that drifts is worse than a query: staff would quote it in a
        // dispute and be wrong.
        $offers = MemberOffer::where('property_id', $property->id);

        return view('admin.properties.show', [
            'property' => $property,
            'stats'    => [
                'views'  => PropertyView::where('property_id', $property->id)->count(),
                'clicks' => TrackingEvent::where('subject_reference', $property->reference)
                    ->whereIn('event_type', [
                        ActivityType::PropertyViewed->value,
                        ActivityType::AdvertisementClicked->value,
                    ])->count(),
                // The pivot, not the wishlist: a Wishlist is a named list a
                // member owns, and the save itself lives in wishlist_properties.
                'saves'  => DB::table('wishlist_properties')->where('property_id', $property->id)->count(),
                'offers' => (clone $offers)->count(),
            ],
            'recentOffers' => (clone $offers)->with('buyer:id,name,email')
                ->latest()->limit(10)->get(),
            'documents' => \App\Models\MemberDocument::forProperty($property->id)
                ->with('uploadedBy:id,email')->latest()->get(),
            'storageDurable' => \App\Support\Storage\DocumentStorage::isDurable(),
            'snapshots' => PropertySnapshot::where('property_id', $property->id)
                ->latest()->limit(20)->get(),
        ]);
    }

    /**
     * Move a listing along its lifecycle.
     *
     * Status is a deliberate action with a check behind it, not a dropdown.
     * The dropdown let anyone set a listing to Active with no photos and no
     * dates, which spends part of a paid advertising period on an
     * advertisement nobody can act on.
     */
    /**
     * Permanently remove a listing.
     *
     * Archiving is the right answer for a listing that simply finished: it
     * stops being advertised and the record stays. Deletion is for the ones
     * that should never have existed — a duplicate, a test listing, a draft
     * built against the wrong member.
     *
     * It is refused once the listing has been advertised under a paid order.
     * The advertising periods and the snapshots of what was published are how
     * "the service was delivered" is proved if the member disputes the charge,
     * and they are anchored to this row. Deleting it removes the proof of the
     * thing the money was for, and nobody discovers that until the dispute
     * arrives. Those listings archive instead.
     *
     * Storage is cleared as part of the delete, so the bucket does not
     * accumulate photos belonging to a listing that no longer exists.
     */
    public function destroy(Request $request, Property $property): RedirectResponse
    {
        $advertised = \App\Models\AdvertisingPeriod::where('property_id', $property->id)->count();
        $snapshots  = PropertySnapshot::where('property_id', $property->id)->count();

        if ($advertised > 0 || $snapshots > 0) {
            return back()->withErrors([
                'delete' => 'This listing cannot be deleted because it has been advertised under a paid order ('
                    .$advertised.' advertising period(s), '.$snapshots.' published snapshot(s)). '
                    .'Those records are what prove the advertising was delivered if the charge is ever disputed. '
                    .'Archive it instead — it stops being advertised and the record is kept.',
            ]);
        }

        // Logged before anything is removed, so the record of what was deleted
        // outlives the thing it describes.
        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'property.deleted',
            subject:   $property,
            payload:   [
                'property_id' => $property->id,
                'reference'   => $property->reference,
                'title'       => $property->title,
                'host_id'     => $property->host_id,
                'status'      => $property->status->value,
                'photos'      => $property->photos()->count(),
                'reason'      => $request->string('reason')->toString() ?: null,
            ],
            ipAddress: $request->ip(),
        );

        $reference = $property->reference;

        DB::transaction(function () use ($property) {
            // Stored objects first. A row removed while its file stays behind
            // leaves an orphan nobody will ever find to clean up.
            foreach ($property->photos as $photo) {
                if ($photo->isUploaded()) {
                    \Illuminate\Support\Facades\Storage::disk($photo->disk)
                        ->delete(array_filter([$photo->path, $photo->original_path]));
                }
            }

            foreach (\App\Models\MemberDocument::forProperty($property->id)->get() as $document) {
                \Illuminate\Support\Facades\Storage::disk($document->disk)->delete($document->path);
                $document->delete();
            }

            $property->photos()->delete();
            $property->availabilityWeeks()->delete();
            $property->amenities()->detach();

            // Offers are correspondence between two people and outlive the
            // advertisement they were made against; the listing reference is
            // already recorded on them.
            MemberOffer::where('property_id', $property->id)->update(['property_id' => null]);

            $property->delete();
        });

        return redirect()
            ->route('admin.properties.index')
            ->with('success', "Deleted listing {$reference}.");
    }

    public function transition(Request $request, Property $property): RedirectResponse
    {
        $this->authorizeListing($request, $property);

        $to = $request->validate([
            'to' => ['required', Rule::in(['draft', 'pending_review', 'active', 'paused'])],
        ])['to'];

        $from = $property->status->value;

        // Going live is the only transition that is refused, and only for
        // reasons the person can act on. Everything else is staff choosing to
        // take something down, which never needs permission.
        if ($to === 'active') {
            $blockers = ListingReadiness::blockers($property);

            if ($blockers !== []) {
                return back()->withErrors(['status' => 'Not ready to activate: '.implode(' ', $blockers)]);
            }
        }

        $property->forceFill(['status' => PropertyStatus::from($to)])->save();

        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'property.status_changed',
            subject:   $property,
            payload:   ['reference' => $property->reference, 'from' => $from, 'to' => $to],
            ipAddress: $request->ip(),
        );

        // Only the two transitions that change what the public sees are worth
        // a place on the activity trail; draft-to-review is internal.
        $activity = match ($to) {
            'active' => ActivityType::AdvertisementActivated,
            'paused' => ActivityType::AdvertisementPaused,
            default  => null,
        };

        if ($activity) {
            app(\App\Services\Tracking\ActivityRecorder::class)->record(
                $activity,
                $request,
                subjectType: 'property',
                subjectReference: $property->reference,
                result: 'completed',
                metadata: ['from' => $from],
            );
        }

        return back()->with('success', match ($to) {
            'draft'          => 'Saved as a draft. It is not advertised.',
            'pending_review' => 'Submitted for review.',
            'active'         => 'Listing is live.',
            'paused'         => 'Listing paused. It is no longer advertised.',
        });
    }
}
