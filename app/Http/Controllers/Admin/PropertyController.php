<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminPropertyRequest;
use App\Mail\ListingCreatedForOwner;
use App\Models\Property;
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
                'base_nightly_cents' => (int) round(((float) $data['nightly_dollars']) * 100),
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
}
