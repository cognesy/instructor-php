<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Delivery\Progress;

readonly final class RuntimeProgressUpdate
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public RuntimeProgressStatus $status,
        public string $source,
        public string $eventType,
        public string $message,
        public ?string $operationId = null,
        public array $payload = [],
    ) {}
}
