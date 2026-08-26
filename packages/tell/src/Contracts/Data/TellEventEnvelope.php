<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts\Data;

use Cognesy\Tell\TellExecutionMode;
use DateTimeImmutable;

/** Normalized backend-neutral event; it deliberately has no raw source object. */
final readonly class TellEventEnvelope
{
    /** @param array<string, int|float|string|bool|null> $metadata */
    public function __construct(
        public string $schema,
        public string $kind,
        public int $sequence,
        public string $executionId,
        public ?string $branch,
        public ?string $session,
        public DateTimeImmutable $occurredAt,
        public array $metadata,
        public ?string $terminal,
        public TellExecutionMode $mode,
        public string $agent,
    ) {}

    /** @param array{schema: string, kind: string, sequence: int, executionId: string, branch: ?string, session: ?string, timestamp: string, metadata: array<string, int|float|string|bool|null>, terminal: ?string} $envelope */
    public static function fromNormalized(array $envelope, TellExecutionMode $mode, string $agent): self
    {
        return new self(
            schema: $envelope['schema'],
            kind: $envelope['kind'],
            sequence: $envelope['sequence'],
            executionId: $envelope['executionId'],
            branch: $envelope['branch'],
            session: $envelope['session'],
            occurredAt: new DateTimeImmutable($envelope['timestamp']),
            metadata: $envelope['metadata'],
            terminal: $envelope['terminal'],
            mode: $mode,
            agent: $agent,
        );
    }

    /** @return array{schema: string, kind: string, sequence: int, executionId: string, branch: ?string, session: ?string, timestamp: string, metadata: array<string, int|float|string|bool|null>, terminal: ?string, mode: string, agent: string} */
    public function toArray(): array
    {
        return [
            'schema' => $this->schema,
            'kind' => $this->kind,
            'sequence' => $this->sequence,
            'executionId' => $this->executionId,
            'branch' => $this->branch,
            'session' => $this->session,
            'timestamp' => $this->occurredAt->format(DATE_ATOM),
            'metadata' => $this->metadata,
            'terminal' => $this->terminal,
            'mode' => $this->mode->value,
            'agent' => $this->agent,
        ];
    }
}
