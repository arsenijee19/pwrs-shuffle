<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use RuntimeException;

final class MailDeliveryException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $outcomeUnknown = false,
        public readonly string $errorCode = 'smtp_failed'
    ) {
        parent::__construct($message);
    }
}
