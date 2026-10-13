<?php

namespace App\Domain;

use RuntimeException;

final class RuleViolation extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
