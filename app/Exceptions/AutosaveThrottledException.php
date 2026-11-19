<?php

namespace App\Exceptions;

use RuntimeException;

class AutosaveThrottledException extends RuntimeException
{
    public function __construct(
        protected readonly int $retryAfter,
        string $message = 'Autosave temporarily throttled.'
    ) {
        parent::__construct($message, 409);
    }

    public function retryAfter(): int
    {
        return $this->retryAfter;
    }
}
