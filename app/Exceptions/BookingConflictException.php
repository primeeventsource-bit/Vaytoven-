<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BookingConflictException extends HttpException
{
    public function __construct(string $message = 'Those dates are no longer available.')
    {
        parent::__construct(409, $message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'booking_conflict',
        ], 409);
    }
}
