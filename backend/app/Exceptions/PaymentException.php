<?php

namespace App\Exceptions;

use Exception;

/** Thrown when Stripe (or another payment processor) returns an error. */
class PaymentException extends Exception
{
}
