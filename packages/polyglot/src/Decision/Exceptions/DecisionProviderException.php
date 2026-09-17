<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Exceptions;

use RuntimeException;

abstract class DecisionProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?string $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    public function isRetriable(): bool
    {
        return false;
    }
}
