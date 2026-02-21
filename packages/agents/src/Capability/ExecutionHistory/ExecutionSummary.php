<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\ExecutionHistory;

use Cognesy\Agents\Data\ExecutionId;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;

/**
 * Lightweight summary of a completed execution, suitable for storage and querying.
 */
final readonly class ExecutionSummary
{
    public function __construct(
        public ExecutionId $executionId,
        public int $executionNumber,
        public ExecutionStatus $status,
        public int $stepCount,
        public Usage $usage,
        public float $duration,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $completedAt,
        public ?string $stopReason,
        public ?string $stopMessage,
        public int $errorCount,
    ) {}

    /**
     * Build a summary from the agent state at execution end.
     *
     * NOTE: AfterExecution hooks fire before withExecutionCompleted() sets the
     * final status, so we derive it from stop signals and error state.
     */
    public static function fromState(AgentState $state): self
    {
        $execution = $state->execution();
        $signal = $state->lastStopSignal();

        $status = match (true) {
            $execution?->isFailed() => ExecutionStatus::Failed,
            $execution?->hasErrors() => ExecutionStatus::Failed,
            $signal?->reason->wasForceStopped() => ExecutionStatus::Stopped,
            default => ExecutionStatus::Completed,
        };

        return new self(
            executionId: $execution?->executionId() ?? ExecutionId::generate(),
            executionNumber: $state->executionCount(),
            status: $status,
            stepCount: $state->stepCount(),
            usage: $state->usage(),
            duration: $execution?->totalDuration() ?? 0.0,
            startedAt: $execution?->startedAt() ?? new DateTimeImmutable(),
            completedAt: $execution?->completedAt() ?? new DateTimeImmutable(),
            stopReason: $signal?->reason->value,
            stopMessage: $signal?->message,
            errorCount: $state->errors()->count(),
        );
    }

    public function toArray(): array
    {
        return [
            'executionId' => $this->executionId->toString(),
            'executionNumber' => $this->executionNumber,
            'status' => $this->status->value,
            'stepCount' => $this->stepCount,
            'usage' => $this->usage->toArray(),
            'duration' => $this->duration,
            'startedAt' => $this->startedAt->format(DateTimeImmutable::ATOM),
            'completedAt' => $this->completedAt?->format(DateTimeImmutable::ATOM),
            'stopReason' => $this->stopReason,
            'stopMessage' => $this->stopMessage,
            'errorCount' => $this->errorCount,
        ];
    }
}
