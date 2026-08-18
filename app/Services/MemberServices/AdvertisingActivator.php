<?php

namespace App\Services\MemberServices;

use App\Enums\AdvertisingPeriodStatus;
use App\Enums\MemberServiceOrderStatus;
use App\Models\AdvertisingPeriod;
use App\Models\MemberServiceOrder;
use App\Models\Property;
use App\Enums\ActivityType;
use App\Models\User;
use App\Services\Tracking\ActivityRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns a paid order into running advertising.
 *
 * The order says a member bought N weeks. This decides which properties those
 * weeks cover and when the window runs, and writes a row per property that
 * staff and a chargeback response can both read.
 */
class AdvertisingActivator
{
    /**
     * @param  Collection<int, Property>  $properties
     * @throws RuntimeException when the order is unpaid or over its allowance
     */
    public function activate(
        MemberServiceOrder $order,
        Collection $properties,
        User $actor,
        ?Carbon $startsAt = null,
    ): Collection {
        // Advertising that was never paid for is the one thing this must not
        // create: it would make the fulfilment record say a service was
        // delivered against a transaction that does not exist.
        if ($order->status !== MemberServiceOrderStatus::Paid) {
            throw new RuntimeException('Only a paid order can be activated.');
        }

        if ($properties->isEmpty()) {
            throw new RuntimeException('Choose at least one property to advertise.');
        }

        $allowance = $order->package->propertyCount();

        if ($properties->count() > $allowance) {
            throw new RuntimeException(sprintf(
                'The %s package covers %d %s; %d were selected.',
                $order->package->label(),
                $allowance,
                \Illuminate\Support\Str::plural('property', $allowance),
                $properties->count(),
            ));
        }

        $startsAt ??= now();

        // Weeks bought, from the order. Not recomputed from the price — an
        // order that was manually corrected must still advertise what it says.
        $endsAt = $startsAt->copy()->addWeeks($order->weeks);

        return DB::transaction(function () use ($order, $properties, $actor, $startsAt, $endsAt) {
            return $properties->map(function (Property $property) use ($order, $actor, $startsAt, $endsAt) {
                $period = AdvertisingPeriod::create([
                    'member_service_order_id' => $order->id,
                    'property_id'             => $property->id,
                    'starts_at'               => $startsAt,
                    'ends_at'                 => $endsAt,
                    'activated_at'            => now(),
                    'activated_by_user_id'    => $actor->id,
                    'status'                  => AdvertisingPeriodStatus::Active,
                ]);

                // Freeze the ad as it goes live. Without this, a dispute six
                // months from now could only be answered with the CURRENT
                // listing, which may share nothing with what actually ran
                // during the period the member paid for.
                app(\App\Services\Listings\PropertySnapshotter::class)->capture(
                    $property,
                    \App\Models\PropertySnapshot::REASON_ACTIVATED,
                    $actor,
                );

                // Recorded after the snapshot, so the activity row and the frozen
                // copy of the advertisement describe the same moment.
                app(ActivityRecorder::class)->record(
                    ActivityType::AdvertisementActivated,
                    subjectType: 'property',
                    subjectReference: $property->reference,
                    result: 'completed',
                    metadata: ['order' => $order->reference],
                );

                return $period;
            });
        });
    }

    /** Push an existing window out by a number of weeks. */
    public function extend(AdvertisingPeriod $period, int $weeks, User $actor): AdvertisingPeriod
    {
        if ($weeks < 1) {
            throw new RuntimeException('Extend by at least one week.');
        }

        // From the later of now and the current end, so extending a period
        // that already lapsed gives the member the full extra time rather than
        // silently burning it against days that have already passed.
        $base = $period->ends_at->isPast() ? now() : $period->ends_at;

        $period->update([
            'ends_at'     => $base->copy()->addWeeks($weeks),
            'status'      => AdvertisingPeriodStatus::Active,
            'staff_notes' => trim(($period->staff_notes ?? '')
                ."\nExtended {$weeks}w by {$actor->email} on ".now()->toDateTimeString()),
        ]);

        return $period->refresh();
    }

    public function pause(AdvertisingPeriod $period, User $actor): AdvertisingPeriod
    {
        app(ActivityRecorder::class)->record(
            ActivityType::AdvertisementPaused,
            subjectType: 'property',
            subjectReference: $period->property?->reference,
            result: 'completed',
        );

        $period->update([
            'status'      => AdvertisingPeriodStatus::Paused,
            'paused_at'   => now(),
            'staff_notes' => trim(($period->staff_notes ?? '')
                ."\nPaused by {$actor->email} on ".now()->toDateTimeString()),
        ]);

        return $period->refresh();
    }

    /**
     * Resume, giving back the days lost to the pause.
     *
     * Without this the member silently loses whatever time the pause consumed
     * — they paid for a number of weeks of advertising, not for a number of
     * weeks on the calendar.
     */
    public function resume(AdvertisingPeriod $period, User $actor): AdvertisingPeriod
    {
        if ($period->status !== AdvertisingPeriodStatus::Paused || ! $period->paused_at) {
            return $period;
        }

        $lostDays = (int) $period->paused_at->diffInDays(now());

        $period->update([
            'status'      => AdvertisingPeriodStatus::Active,
            'ends_at'     => $period->ends_at->copy()->addDays($lostDays),
            'paused_at'   => null,
            'staff_notes' => trim(($period->staff_notes ?? '')
                ."\nResumed by {$actor->email} on ".now()->toDateTimeString()
                ." (+{$lostDays}d returned)"),
        ]);

        return $period->refresh();
    }
}
