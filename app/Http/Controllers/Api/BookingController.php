<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Property;
use App\Services\Bookings\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return BookingResource::collection(
            Booking::query()
                ->where('traveler_id', $request->user()->id)
                ->latest('check_in_date')
                ->paginate(20)
        );
    }

    public function store(StoreBookingRequest $request): BookingResource|JsonResponse
    {
        $property = Property::findOrFail($request->integer('property_id'));

        $booking = $this->bookings->create(
            traveler: $request->user(),
            property: $property,
            checkIn: $request->string('check_in_date')->toString(),
            checkOut: $request->string('check_out_date')->toString(),
            guests: $request->integer('guests'),
        );

        return (new BookingResource($booking))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Booking $booking): BookingResource
    {
        // A traveler can only see their own bookings.
        if ($booking->traveler_id !== $request->user()->id) {
            abort(404);
        }

        return new BookingResource($booking);
    }

    public function cancel(Request $request, Booking $booking): BookingResource
    {
        if ($booking->traveler_id !== $request->user()->id) {
            abort(404);
        }

        if ($booking->status->isTerminal()) {
            abort(422, 'Booking is already in a terminal state.');
        }

        $booking->transitionTo(
            next: BookingStatus::Cancelled,
            actorUserId: $request->user()->id,
            reason: $request->string('reason')->toString() ?: 'traveler_cancelled',
        );

        return new BookingResource($booking->refresh());
    }
}
