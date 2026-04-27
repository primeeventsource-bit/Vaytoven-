<?php

namespace App\Exceptions;

use Exception;

/** Thrown when a booking operation fails for business reasons (collision, bad state, etc.) */
class BookingException extends Exception
{
}
