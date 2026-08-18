<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityType;
use App\Enums\AvailabilityWeekStatus;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyAvailabilityWeek;
use App\Services\AdminAuditLogService;
use App\Services\Tracking\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The time a property is advertised for.
 *
 * Members may manage their own; staff may manage anyone's. Same rule as the
 * listing builder, and for the same reason — properties.edit is granted to the
 * RBAC host role, so the permission alone would let any host edit any member's
 * dates.
 */
class PropertyAvailabilityController extends Controller
{
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

        $validated = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on'   => ['required', 'date', 'after:starts_on'],
            'status'    => ['nullable', Rule::enum(AvailabilityWeekStatus::class)],
            'notes'     => ['nullable', 'string', 'max:500'],
        ], [
            'ends_on.after' => 'The week has to end after it starts.',
        ]);

        // Advertising dates that have already passed is a typo, not a choice.
        // Caught here rather than left to look like a working entry that no
        // traveler can ever act on.
        if (strtotime($validated['ends_on']) < strtotime('today')) {
            return back()->withInput()->withErrors([
                'ends_on' => 'That week has already ended. Advertised time has to be in the future.',
            ]);
        }

        // Overlap, not just exact duplication. The unique index catches two
        // identical rows; it does not catch Sep 5-12 sitting across Sep 8-15,
        // which would let a traveler make offers on two listings for the same
        // nights and leave the member to discover the clash afterwards.
        // whereDate, not where. The columns carry a time component, so a
        // plain string comparison makes '2026-09-25 00:00:00' greater than
        // '2026-09-25' and rejects legitimate back-to-back weeks, where one
        // ends the day the next begins.
        $clash = PropertyAvailabilityWeek::where('property_id', $property->id)
            ->whereDate('starts_on', '<', $validated['ends_on'])
            ->whereDate('ends_on', '>', $validated['starts_on'])
            ->first();

        if ($clash) {
            return back()->withInput()->withErrors([
                'starts_on' => "Those dates overlap a week already listed ({$clash->label()}).",
            ]);
        }

        $week = PropertyAvailabilityWeek::create([
            'property_id'        => $property->id,
            'starts_on'          => $validated['starts_on'],
            'ends_on'            => $validated['ends_on'],
            'status'             => $validated['status'] ?? AvailabilityWeekStatus::Available->value,
            'notes'              => $validated['notes'] ?? null,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        $this->recordChange($request, $property, 'added', $week);

        return back()->with('success', "Added {$week->label()}.");
    }

    public function update(Request $request, Property $property, PropertyAvailabilityWeek $week): RedirectResponse
    {
        $this->authorizeListing($request, $property);
        abort_unless($week->property_id === $property->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(AvailabilityWeekStatus::class)],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $week->update([
            'status'             => $validated['status'],
            'notes'              => $validated['notes'] ?? $week->notes,
            // Recorded on every change, because "the member withdrew this" and
            // "staff closed this" are different facts after the event.
            'updated_by_user_id' => $request->user()?->id,
        ]);

        $this->recordChange($request, $property, 'status changed', $week);

        return back()->with('success', "{$week->label()} is now {$week->status->label()}.");
    }

    public function destroy(Request $request, Property $property, PropertyAvailabilityWeek $week): RedirectResponse
    {
        $this->authorizeListing($request, $property);
        abort_unless($week->property_id === $property->id, 404);

        $label = $week->label();

        // Audited before deletion so the record of what was removed survives
        // the thing it describes.
        $this->recordChange($request, $property, 'removed', $week);

        $week->delete();

        return back()->with('success', "Removed {$label}.");
    }

    private function recordChange(Request $request, Property $property, string $what, PropertyAvailabilityWeek $week): void
    {
        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'property_availability.'.str_replace(' ', '_', $what),
            subject:   $property,
            payload:   [
                'week'   => $week->label(),
                'status' => $week->status->value,
            ],
            ipAddress: $request->ip(),
        );

        app(ActivityRecorder::class)->record(
            ActivityType::AvailabilityChanged,
            $request,
            subjectType: 'property',
            subjectReference: $property->reference,
            result: 'completed',
            metadata: ['week' => $week->label(), 'change' => $what, 'status' => $week->status->value],
        );
    }
}
