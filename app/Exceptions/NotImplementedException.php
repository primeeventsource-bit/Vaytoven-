<?php

namespace App\Exceptions;

use RuntimeException;

class NotImplementedException extends RuntimeException
{
    public static function for(string $class, string $reason = 'Not implemented yet'): self
    {
        return new self("{$class}: {$reason}");
    }
}
