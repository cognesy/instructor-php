<?php

declare(strict_types=1);

namespace Cognesy\Tell;

use DateTimeImmutable;

/** A typed observation of one event emitted while Tell executes a request. */
final readonly class TellEvent
{
    /** @param array{schema: string, kind: string, sequence: int, executionId: string, branch: ?string, session: ?string, timestamp: string, metadata: array<string, int|float|string|bool|null>, terminal: ?string, mode: string, agent: string} $envelope */
    public function __construct(
        private array $envelope,
        private ?object $source = null,
        private ?string $workspace = null,
    ) {}

    public function type(): string
    {
        return $this->envelope['kind'];
    }

    /** @return array<string, int|float|string|bool|null> */
    public function data(): array
    {
        return $this->envelope['metadata'];
    }

    public function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->envelope['timestamp']);
    }

    public function mode(): TellExecutionMode
    {
        return TellExecutionMode::from($this->envelope['mode']);
    }

    public function agent(): string
    {
        return $this->envelope['agent'];
    }

    public function session(): ?string
    {
        return $this->envelope['session'];
    }

    public function workspace(): ?string
    {
        return $this->workspace;
    }

    /**
     * Returns the framework event for callers that need its provider-specific fields.
     */
    public function source(): object
    {
        return $this->source ?? throw new \LogicException('This normalized Tell event has no source event.');
    }

    /** @return array{schema: string, kind: string, sequence: int, executionId: string, branch: ?string, session: ?string, timestamp: string, metadata: array<string, int|float|string|bool|null>, terminal: ?string, mode: string, agent: string} */
    public function envelope(): array
    {
        return $this->envelope;
    }
}
