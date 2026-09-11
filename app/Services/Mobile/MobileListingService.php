<?php

namespace App\Services\Mobile;

use App\Enums\ActivityType;
use App\Enums\AvailabilityWeekStatus;
use App\Enums\PropertyStatus;
use App\Http\Requests\Api\Mobile\OfferRequest;
use App\Models\MemberOffer;
use App\Models\Property;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\Offers\OfferService;
use App\Services\Tracking\ActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileListingService
{
    public function save(Request $request, Property $property, bool $saved): void
    {
        abort_unless(! $saved || $property->status === PropertyStatus::Active, 404);
        DB::transaction(function () use ($request, $property, $saved) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $list = Wishlist::firstOrCreate(['user_id' => $request->user()->id], ['name' => 'Saved', 'is_private' => true]);
            $exists = $list->properties()->where('properties.id', $property->id)->exists();
            if ($exists === $saved) {
                return;
            }
            if ($saved) {
                $list->properties()->attach($property->id, ['added_at' => now()]);
            } else {
                $list->properties()->detach($property->id);
            }
            app(ActivityRecorder::class)->record($saved ? ActivityType::FavoriteSaved : ActivityType::FavoriteRemoved, $request, subjectType: 'property', subjectReference: $property->reference);
        });
    }

    public function submit(OfferRequest $request, Property $property): MemberOffer
    {
        return DB::transaction(function () use ($request, $property) {
            $property = Property::whereKey($property->id)->lockForUpdate()->firstOrFail();
            abort_unless($property->status === PropertyStatus::Active, 404);
            abort_if($property->host_id === $request->user()->id, 403, __('You cannot submit an offer on your own listing.'));
            $week = $request->filled('availability_week_id') ? $property->availabilityWeeks()->whereKey($request->integer('availability_week_id'))->lockForUpdate()->first() : null;
            if ($request->filled('availability_week_id')) {
                abort_unless($week && $week->status->acceptsOffers(), 422, __('This week is no longer taking offers.'));
            }
            $offer = app(OfferService::class)->submit(
                property: $property, buyer: $request->user(), kind: $request->kind(), amountCents: $request->amountCents(),
                message: $request->validated('message'), ipAddress: $request->ip(), checkIn: $request->validated('check_in'), checkOut: $request->validated('check_out'),
                guests: $request->filled('guests') ? $request->integer('guests') : null, availabilityWeekId: $week?->id,
            );
            $week?->update(['status' => AvailabilityWeekStatus::OfferPending, 'updated_by_user_id' => $request->user()->id]);
            app(ActivityRecorder::class)->record(ActivityType::OfferSubmitted, $request, subjectType: 'property', subjectReference: $property->reference);

            return $offer->load('property');
        });
    }

    public function respond(Request $request, MemberOffer $offer): MemberOffer
    {
        return DB::transaction(function () use ($request, $offer) {
            $offer = MemberOffer::whereKey($offer->id)->lockForUpdate()->firstOrFail();
            abort_unless($offer->isFromBuyer(), 404);
            abort_unless($offer->property?->host_id === $request->user()->id, 403);
            abort_unless($offer->isAwaitingOwner(), 422, __('This offer is no longer open.'));
            $service = app(OfferService::class);
            $method = $request->input('decision') === 'accepted' ? 'accept' : 'decline';

            return $service->$method($offer, $request->user(), $request->input('notes'), $request->ip())->load('property');
        });
    }
}
