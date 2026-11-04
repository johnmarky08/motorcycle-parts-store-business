<?php

namespace App\Services\Ml;

use RuntimeException;

/** A failure that is safe to show to the user (message) plus a machine-readable code. */
class MlException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'ml_error')
    {
        parent::__construct($message);
    }
}
