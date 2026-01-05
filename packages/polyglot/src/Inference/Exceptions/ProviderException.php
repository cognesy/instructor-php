<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Exceptions;

use RuntimeException;

abstract class ProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?array $payload = null,
    ) {
        parent::__construct($message);
    }

    public function isRetriable(): bool {
        return false;
    }
}
