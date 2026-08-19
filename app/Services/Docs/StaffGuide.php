<?php

namespace App\Services\Docs;

use App\Enums\ActivityType;
use App\Enums\AvailabilityWeekStatus;
use App\Enums\MemberServicePackage;
use App\Enums\PropertyStatus;
use App\Models\MemberDocument;
use App\Models\PropertyPhoto;
use App\Models\Role;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The staff training guide, as a PDF.
 *
 * Written to be handed to somebody on their first morning, so it explains what
 * each screen is for and what the rules are, not which buttons exist — a
 * screenshot tour is out of date the week after it is printed and teaches
 * nobody why a listing cannot go live without a photo.
 *
 * Everything structural is read from the application rather than typed here:
 * the roles and their permissions come from the roles table, the listing
 * statuses from the enum the code branches on, the document categories from the
 * model that validates uploads. A guide that lists a role nobody has, or omits
 * one everybody has, is worse than no guide — staff trust it and act on it. The
 * prose around those lists is authored, because "what does Paused mean and when
 * would I use it" is not derivable from a string.
 *
 * It is generated on demand rather than checked in as a file, so the copy
 * somebody downloads on any given day describes the system as it is that day.
 */
class StaffGuide
{
    public function render(): string
    {
        return Pdf::loadView('docs.staff-guide', $this->data())
            ->setPaper('letter', 'portrait')
            ->output();
    }

    public function filename(): string
    {
        return 'vaytoven-staff-guide-'.Carbon::now()
            ->setTimezone(config('app.display_timezone', 'America/New_York'))
            ->format('Y-m-d').'.pdf';
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return [
            'generatedAt'   => Carbon::now(),
            'roles'         => $this->roles(),
            'navigation'    => $this->navigation(),
            'listingStages' => $this->listingStages(),
            'weekStates'    => $this->weekStates(),
            'packages'      => MemberServicePackage::cases(),
            'photoSections' => PropertyPhoto::CATEGORIES,
            'documentTypes' => MemberDocument::CATEGORIES,
            'activityGroups' => $this->activityGroups(),
            'neverReportable' => ActivityType::evidenceTrail(),
        ];
    }

    /**
     * The roles that actually exist, with what each one can do.
     *
     * Read from the database rather than from the seeder, because the seeder is
     * the starting point and an administrator may have changed a role since.
     * An empty result means RBAC has not been seeded on this environment, which
     * the template says out loud rather than printing a blank page.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function roles(): Collection
    {
        return Role::query()
            ->with('permissions:id,key')
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role) => [
                'name'        => $role->name,
                'key'         => $role->key,
                'permissions' => $role->permissions->pluck('key')->sort()->values()->all(),
            ]);
    }

    /**
     * The admin tabs, and what each is for.
     *
     * Kept in the same order and with the same permission keys as
     * partials/admin-nav, so "I do not see the Contracts tab" has an answer
     * here: the permission beside it is the one that is missing.
     *
     * @return array<int, array<string, string>>
     */
    private function navigation(): array
    {
        return [
            ['label' => 'Users', 'permission' => 'users.view', 'purpose' =>
                'Everyone with an account — members, hosts, travellers and staff. Open a person to see their listings, orders, offers, contracts, documents and login history in one place. New staff accounts are created here.'],

            ['label' => 'Roles', 'permission' => 'roles.view', 'purpose' =>
                'What each role is allowed to do. Changing a role changes it for everybody holding it, immediately.'],

            ['label' => 'Listings', 'permission' => 'properties.view', 'purpose' =>
                'Every advertised property. This is where a listing is built: details, photos, description, amenities, availability and offer settings, then published.'],

            ['label' => 'Offers', 'permission' => 'offers.view', 'purpose' =>
                'Enquiries and offers travellers have submitted against listings. Vaytoven does not take reservations — an offer is a message about a week, and the member and the traveller settle it between themselves.'],

            ['label' => 'Orders', 'permission' => 'billing.view', 'purpose' =>
                'Member Services purchases: which package, how many weeks, what was charged and whether it cleared. This is the one place money changes hands.'],

            ['label' => 'Contracts', 'permission' => 'contracts.view', 'purpose' =>
                'Agreements sent for signature and the signed copies that came back, with the signing event history.'],

            ['label' => 'Inbox', 'permission' => 'inbox.view', 'purpose' =>
                'Messages from the public forms — contact, trip support, host enquiries — each with a reference the customer can quote.'],

            ['label' => 'Activity', 'permission' => 'audit.view', 'purpose' =>
                'What staff have done: who edited which listing, who downloaded which document, who changed a role.'],

            ['label' => 'Activity & IP logs', 'permission' => 'audit.view', 'purpose' =>
                'The full website activity log, including visitors. Filter by activity type, follow one session end to end, or view activity on a map. This is the screen a dispute is answered from.'],

            ['label' => 'Settings', 'permission' => 'settings.view', 'purpose' =>
                'Prices, fees, email templates, payment processor configuration and feature flags.'],
        ];
    }

    /**
     * The listing lifecycle.
     *
     * Derived from PropertyStatus so a new status cannot appear in the code
     * without appearing here, with authored guidance on when each is right.
     *
     * @return array<int, array<string, string>>
     */
    private function listingStages(): array
    {
        $guidance = [
            'draft' => 'Being built. Invisible to the public. Staff and the owner can preview it at its real URL, which is the point of previewing — after it is live is too late to catch the typo.',
            'pending_review' => 'Finished and waiting on somebody to check it before it goes out.',
            'active' => 'Advertised. It appears in search, on the map, and on the Event Centers city pages. A listing cannot reach this state without a title, a description, a city, at least one photo and at least one upcoming available week — the builder lists whatever is missing.',
            'paused' => 'Taken out of the public site temporarily, with everything kept. Use this when an owner wants a break; it is reversible and loses nothing.',
            'archived' => 'Retired. Kept for the record but no longer advertised, and not expected to come back.',
        ];

        return array_map(
            fn (PropertyStatus $status) => [
                'label'    => ucwords(str_replace('_', ' ', $status->value)),
                'value'    => $status->value,
                'guidance' => $guidance[$status->value] ?? '',
            ],
            PropertyStatus::cases(),
        );
    }

    /**
     * The states a week on the availability calendar can be in.
     *
     * @return array<int, array<string, string>>
     */
    private function weekStates(): array
    {
        $guidance = [
            'available'     => 'Advertised and open to offers.',
            'offer_pending' => 'An offer has arrived. The week stays advertised but stops taking new offers, so the member is not left comparing three bids for the same nights that came in while they were deciding.',
            'unavailable'   => 'The owner is using it, or it is already committed. Not offered.',
            'closed'        => 'Not advertised at all.',
        ];

        return array_map(
            fn (AvailabilityWeekStatus $status) => [
                'label'    => ucwords(str_replace('_', ' ', $status->value)),
                'guidance' => $guidance[$status->value] ?? '',
            ],
            AvailabilityWeekStatus::cases(),
        );
    }

    /**
     * The activity log's filter tabs, with a count of what each covers.
     *
     * @return array<int, array<string, mixed>>
     */
    private function activityGroups(): array
    {
        return collect(ActivityType::groups())
            // 'all' is the no-filter tab, not a category. valuesForGroup has no
            // list for it and answers with a placeholder that is not a real
            // activity type, which would blow up on the from() below.
            ->except(['all'])
            ->map(fn (string $label, string $key) => [
                'key'   => $key,
                'label' => $label,
                'types' => array_map(
                    fn (string $value) => ActivityType::from($value)->label(),
                    ActivityType::valuesForGroup($key),
                ),
            ])
            ->values()
            ->all();
    }
}
