<?php

declare(strict_types=1);

namespace Cognesy\Tell\Tool;

/** Stable bounded result of one direct Tell tool invocation. */
final readonly class TellToolResult
{
    /** @param array<string, mixed> $error */
    public function __construct(
        public string $tool,
        public bool $success,
        public string $operation,
        public string $invokedAs,
        public mixed $data,
        public ?array $error,
        public bool $truncated,
        public bool $partial,
        public string $durationClass,
        public string $effect,
    ) {}

    /** @return array{mode: 'direct', inference: false, durable: false} */
    public function execution(): array
    {
        return ['mode' => 'direct', 'inference' => false, 'durable' => false];
    }
}
