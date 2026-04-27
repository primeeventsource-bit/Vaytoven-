<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BookingException;
use App\Exceptions\PaymentException;
use App\Http\Requests\CreateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Property;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController
{
    public function __construct(private readonly BookingService $bookings)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $tab = $request->string('tab')->toString();

        $query = Booking::query()
            ->where('guest_id', $user->id)
            ->with(['property.coverImage', 'property.host'])
            ->orderByDesc('check_in');

        match ($tab) {
            'upcoming' => $query->upcoming(),
            'past'     => $query->past(),
            default    => null,
        };

        return BookingResource::collection($query->paginate(20));
    }

    public function show(Request $request, string $code): BookingResource
    {
        $booking = Booking::with(['property.images', 'property.host', 'guest', 'host', 'review'])
            ->where('confirmation_code', strtoupper($code))
            ->firstOrFail();

        // Authorize: guest, host, or admin
        $user = $request->user();
        abort_unless(
            $user->id === $booking->guest_id
                || $user->id === $booking->host_id
                || $user->isAdmin(),
            403,
            'You are not part of this booking.'
        );

        return new BookingResource($booking);
    }

    public function store(CreateBookingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $property = Property::published()->where('slug', $data['property_slug'])->firstOrFail();

        try {
            $booking = $this->bookings->create(
                guest:    $request->user(),
                property: $property,
                checkIn:  CarbonImmutable::parse($data['check_in']),
                checkOut: CarbonImmutable::parse($data['check_out']),
                guests:   $data['guests'],
                message:  $data['message'] ?? null,
            );
        } catch (BookingException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(new BookingResource($booking), 201);
    }

    public function pay(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'payment_method_id' => 'required|string|starts_with:pm_',
        ]);

        $booking = Booking::where('confirmation_code', strtoupper($code))
            ->where('guest_id', $request->user()->id)
            ->firstOrFail();

        try {
            $booking = $this->bookings->authorizePayment(
                $booking,
                $request->string('payment_method_id')->toString(),
            );
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage()], 402);
        }

        return response()->json(new BookingResource($booking));
    }

    public function cancel(Request $request, string $code): JsonResponse
    {
        $booking = Booking::where('confirmation_code', strtoupper($code))->firstOrFail();
        $user = $request->user();

        abort_unless(
            $user->id === $booking->guest_id || $user->id === $booking->host_id,
            403,
            'Only the guest or host can cancel this booking.'
        );

        try {
            $booking = $this->bookings->cancel(
                $booking,
                $user,
                $request->string('reason')->toString() ?: null,
            );
        } catch (BookingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(new BookingResource($booking));
    }
}
