<?php

namespace App\Services\Payments\Nmi;

/**
 * Network/HTTP-level failure talking to NMI — distinct from a decline,
 * which is a successful API round-trip with response=2/3.
 */
class NmiTransportException extends \RuntimeException
{
}
