<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

use DateTimeImmutable;

/** Normalized shell-job lifecycle event with redacted scalar metadata only. */
final readonly class TellShellJobEvent
{
    /** @param array<string, int|float|string|bool|null> $metadata */
    public function __construct(
        public string $schema,
        public string $kind,
        public int $sequence,
        public string $sourceId,
        public DateTimeImmutable $occurredAt,
        public array $metadata,
        public ?string $terminal = null,
    ) {}

    /** @return array{schema: string, kind: string, sequence: int, sourceId: string, timestamp: string, metadata: array<string, int|float|string|bool|null>, terminal: ?string} */
    public function toArray(): array {
        return [
            'schema' => $this->schema,
            'kind' => $this->kind,
            'sequence' => $this->sequence,
            'sourceId' => $this->sourceId,
            'timestamp' => $this->occurredAt->format(DATE_ATOM),
            'metadata' => $this->metadata,
            'terminal' => $this->terminal,
        ];
    }
}
