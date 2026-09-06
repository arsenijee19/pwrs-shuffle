<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use RuntimeException;

final class FulfillmentException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus = 409
    ) {
        parent::__construct($message);
    }
}
