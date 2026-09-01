<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

/** Stable bounded result of one direct Tell tool invocation. */
final readonly class TellToolResult
{
    /** @param array<string, mixed> $error */
    public function __construct(
        public string $tool,
        public bool $success,
        public mixed $data,
        public ?array $error,
        public bool $truncated,
        public bool $partial,
        public string $durationClass,
        public string $effect,
    ) {}

    /** @return array{mode: 'direct', inference: false, durable: false} */
    public function execution(): array {
        return ['mode' => 'direct', 'inference' => false, 'durable' => false];
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'tool' => $this->tool,
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error,
            'truncated' => $this->truncated,
            'partial' => $this->partial,
            'durationClass' => $this->durationClass,
            'effect' => $this->effect,
            'execution' => $this->execution(),
        ];
    }
}
