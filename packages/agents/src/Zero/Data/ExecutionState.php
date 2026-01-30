<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero\Data;

use Cognesy\Agents\Core\Collections\StepExecutions;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

/**
 * Execution-specific state that is transient across executions.
 *
 * Contains all data related to a single agent execution run:
 * - Execution identity and timing
 * - Status tracking
 * - Completed step results
 * - Current step being processed
 * - Aggregated usage metrics
 *
 * This data is optional in AgentState - null when the agent is "between executions"
 * (ready for a fresh start). Present during execution or when persisting mid-execution
 * state for resume capability.
 */
final readonly class ExecutionState
{
    public function __construct(
        public string $executionId,
        public AgentStatus $status,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $completedAt,
        public StepExecutions $stepExecutions,

        public ?CurrentExecution $currentExecution,
        public ?AgentStep $currentStep,
    ) {}

    /**
     * Create a fresh execution state for a new execution run.
     */
    public static function withExecutionStarted(): self
    {
        return new self(
            executionId: Uuid::uuid4(),
            status: AgentStatus::InProgress,
            startedAt: new DateTimeImmutable(),
            completedAt: null,
            stepExecutions: StepExecutions::empty(),
            currentExecution: null,
            currentStep: null,
        );
    }

    public function currentStep(): ?AgentStep {
        return $this->currentStep;
    }

    public function currentExecution(): ?CurrentExecution {
        return $this->currentExecution;
    }

    public function stepCount(): int {
        return $this->stepExecutions->count();
    }

    public function continuationOutcome(): ?ContinuationOutcome {
        return $this->stepExecutions->lastOutcome();
    }

    public function stopReason(): ?StopReason {
        return $this->continuationOutcome()?->stopReason();
    }

    public function usage(): Usage {
        $usage = $this->stepExecutions->totalUsage();
        if ($this->currentStep === null) {
            return $usage;
        }
        return $usage->withAccumulated($this->currentStep->usage());
    }

    public function duration(): float {
        return $this->stepExecutions->totalDuration();
    }

    // IMMUTABLE MUTATORS ///////////////////////////////////

    public function with(
        ?string $executionId = null,
        ?AgentStatus $status = null,
        ?DateTimeImmutable $startedAt = null,
        ?DateTimeImmutable $completedAt = null,
        ?StepExecutions $stepExecutions = null,
        ?CurrentExecution $currentExecution = null,
        ?AgentStep $currentStep = null,
    ): self {
        return new self(
            executionId: $executionId ?? $this->executionId,
            status: $status ?? $this->status,
            startedAt: $startedAt ?? $this->startedAt,
            completedAt: $completedAt ?? $this->completedAt,
            stepExecutions: $stepExecutions ?? $this->stepExecutions,
            currentExecution: $currentExecution ?? $this->currentExecution,
            currentStep: $currentStep ?? $this->currentStep,
        );
    }

    public function withCurrentExecution(?CurrentExecution $execution): self {
        return $this->with(currentExecution: $execution);
    }

    public function withCurrentStep(?AgentStep $step): self {
        return $this->with(currentStep: $step);
    }

    // SERIALIZATION ///////////////////////////////////////

    public function toArray(): array
    {
        return [
            'executionId' => $this->executionId,
            'status' => $this->status->value,
            'startedAt' => $this->startedAt->format(DateTimeImmutable::ATOM),
            'completedAt' => $this->completedAt?->format(DateTimeImmutable::ATOM),
            'stepExecutions' => $this->stepExecutions->toArray(),
            'currentExecution' => $this->currentExecution?->toArray(),
            'currentStep' => $this->currentStep?->toArray(),
        ];
    }

    public static function fromArray(array $data): self
    {
        $executionIdValue = $data['executionId'] ?? null;
        $executionId = is_string($executionIdValue) && $executionIdValue !== ''
            ? $executionIdValue
            : Uuid::uuid4();

        $statusValue = $data['status'] ?? null;
        $status = match (true) {
            $statusValue instanceof AgentStatus => $statusValue,
            is_string($statusValue) && $statusValue !== '' => AgentStatus::from($statusValue),
            default => AgentStatus::InProgress,
        };

        $startedAtValue = $data['startedAt'] ?? null;
        $startedAt = match (true) {
            $startedAtValue instanceof DateTimeImmutable => $startedAtValue,
            is_string($startedAtValue) && $startedAtValue !== '' => new DateTimeImmutable($startedAtValue),
            default => new DateTimeImmutable(),
        };

        $completedAtValue = $data['completedAt'] ?? null;
        $completedAt = match (true) {
            $completedAtValue instanceof DateTimeImmutable => $completedAtValue,
            is_string($completedAtValue) && $completedAtValue !== '' => new DateTimeImmutable($completedAtValue),
            default => null,
        };

        $stepExecutionsData = $data['stepExecutions'] ?? null;
        $stepExecutions = is_array($stepExecutionsData)
            ? StepExecutions::fromArray($stepExecutionsData)
            : StepExecutions::empty();

        $currentExecutionData = $data['currentExecution'] ?? null;
        $currentExecution = is_array($currentExecutionData)
            ? CurrentExecution::fromArray($currentExecutionData)
            : null;

        $currentStepData = $data['currentStep'] ?? null;
        $currentStep = match (true) {
            is_array($currentStepData) => AgentStep::fromArray($currentStepData),
            default => $stepExecutions->last()?->step,
        };

        return new self(
            executionId: $executionId,
            status: $status,
            startedAt: $startedAt,
            completedAt: $completedAt,
            stepExecutions: $stepExecutions,
            currentExecution: $currentExecution,
            currentStep: $currentStep,
        );
    }

    // INTERNAL ////////////////////////////////////////////


}
