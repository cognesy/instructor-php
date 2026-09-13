<?php

declare(strict_types=1);

namespace Cognesy\Agents\Capability\ExecutionJournal;

use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use DateTimeImmutable;

/** Payload-free semantic transition in an agent execution journal. */
final readonly class ExecutionJournalRecord
{
    public function __construct(
        public string $kind,
        public string $executionId,
        public string $agentId,
        public DateTimeImmutable $occurredAt,
        public ?ExecutionStatus $status = null,
        public int $stepCount = 0,
        public ?string $reason = null,
        public int $errorCount = 0,
        public InferenceUsage $usage = new InferenceUsage(),
        /** @var array<string, int|float|string|bool|null> */
        public array $facts = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'schema' => 'agents.execution.journal.v1',
            'kind' => $this->kind,
            'executionId' => $this->executionId,
            'agentId' => $this->agentId,
            'timestamp' => $this->occurredAt->format(DateTimeImmutable::ATOM),
            'status' => $this->status?->value,
            'steps' => $this->stepCount,
            'reason' => $this->reason,
            'errors' => $this->errorCount,
            'usage' => $this->usage->toArray(),
            'facts' => $this->facts,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): ?self {
        if (($data['schema'] ?? null) !== 'agents.execution.journal.v1') {
            return null;
        }
        $kind = $data['kind'] ?? null;
        $executionId = $data['executionId'] ?? null;
        $agentId = $data['agentId'] ?? null;
        $timestamp = $data['timestamp'] ?? null;
        if (!is_string($kind) || !is_string($executionId) || !is_string($agentId) || !is_string($timestamp)) {
            return null;
        }
        $status = $data['status'] ?? null;
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
        $facts = is_array($data['facts'] ?? null) && !array_is_list($data['facts'])
            ? array_filter(
                $data['facts'],
                static fn (mixed $value): bool => is_int($value) || is_float($value) || is_string($value) || is_bool($value) || is_null($value),
            )
            : [];

        return new self(
            kind: $kind,
            executionId: $executionId,
            agentId: $agentId,
            occurredAt: new DateTimeImmutable($timestamp),
            status: is_string($status) ? ExecutionStatus::tryFrom($status) : null,
            stepCount: is_int($data['steps'] ?? null) ? $data['steps'] : 0,
            reason: is_string($data['reason'] ?? null) ? $data['reason'] : null,
            errorCount: is_int($data['errors'] ?? null) ? $data['errors'] : 0,
            usage: InferenceUsage::fromArray($usage),
            facts: $facts,
        );
    }
}
