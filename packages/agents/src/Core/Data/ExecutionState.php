<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Data;

use Cognesy\Agents\Core\Collections\StepExecutions;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Enums\AgentStatus;
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

    /**
     * Get the current step being processed (if any).
     */
    public function currentStep(): ?AgentStep
    {
        return $this->currentStep;
    }

    /**
     * Get the current execution context (transient step info).
     */
    public function currentExecution(): ?CurrentExecution
    {
        return $this->currentExecution;
    }

    /**
     * Number of completed steps in this execution.
     */
    public function stepCount(): int
    {
        return $this->stepExecutions->count();
    }

    /**
     * Step count including transient current step (used during continuation evaluation).
     */
    public function transientStepCount(): int
    {
        $currentStep = $this->currentStep;
        if ($currentStep === null) {
            return $this->stepCount();
        }
        if ($this->isCurrentStepRecorded($currentStep)) {
            return $this->stepCount();
        }
        return $this->stepCount() + 1;
    }

    /**
     * Get the continuation outcome from the last step execution.
     */
    public function continuationOutcome(): ?ContinuationOutcome
    {
        return $this->stepExecutions->lastOutcome();
    }

    /**
     * Get the stop reason from the last step execution's continuation outcome.
     */
    public function stopReason(): ?StopReason
    {
        return $this->continuationOutcome()?->stopReason();
    }

    /**
     * Aggregate token usage across all steps, including unrecorded current step.
     */
    public function usage(): Usage
    {
        $usage = $this->stepExecutions->totalUsage();
        $currentStep = $this->currentStep;
        if ($currentStep === null) {
            return $usage;
        }
        if ($this->isCurrentStepRecorded($currentStep)) {
            return $usage;
        }
        return $usage->withAccumulated($currentStep->usage());
    }

    /**
     * Duration of this execution in seconds (sum of all step durations).
     */
    public function duration(): float
    {
        return $this->stepExecutions->totalDuration();
    }

    // IMMUTABLE MUTATORS ///////////////////////////////////

    public function withStatus(AgentStatus $status): self
    {
        return new self(
            executionId: $this->executionId,
            status: $status,
            startedAt: $this->startedAt,
            completedAt: $this->completedAt,
            stepExecutions: $this->stepExecutions,
            currentExecution: $this->currentExecution,
            currentStep: $this->currentStep,
        );
    }

    public function withCurrentExecution(?CurrentExecution $execution): self
    {
        return new self(
            executionId: $this->executionId,
            status: $this->status,
            startedAt: $this->startedAt,
            completedAt: $this->completedAt,
            stepExecutions: $this->stepExecutions,
            currentExecution: $execution,
            currentStep: $this->currentStep,
        );
    }

    public function withCurrentStep(?AgentStep $step): self
    {
        return new self(
            executionId: $this->executionId,
            status: $this->status,
            startedAt: $this->startedAt,
            completedAt: $this->completedAt,
            stepExecutions: $this->stepExecutions,
            currentExecution: $this->currentExecution,
            currentStep: $step,
        );
    }

    public function withStepExecution(StepExecution $execution): self
    {
        return new self(
            executionId: $this->executionId,
            status: $this->status,
            startedAt: $this->startedAt,
            completedAt: $this->completedAt,
            stepExecutions: $this->stepExecutions->append($execution),
            currentExecution: null, // Clear transient execution after recording
            currentStep: $execution->step,
        );
    }

    public function withNewStepExecution(): self
    {
        if ($this->currentExecution !== null) {
            return $this;
        }
        $execution = new CurrentExecution(stepNumber: $this->stepCount() + 1);
        return new self(
            executionId: $this->executionId,
            status: $this->status,
            startedAt: $this->startedAt,
            completedAt: $this->completedAt,
            stepExecutions: $this->stepExecutions,
            currentExecution: $execution,
            currentStep: null,
        );
    }

    public function withCurrentExecutionCleared(): self
    {
        return new self(
            executionId: $this->executionId,
            status: $this->status,
            startedAt: $this->startedAt,
            completedAt: $this->completedAt,
            stepExecutions: $this->stepExecutions,
            currentExecution: null,
            currentStep: $this->currentStep,
        );
    }

    public function withCompletedNow(): self
    {
        return new self(
            executionId: $this->executionId,
            status: $this->status,
            startedAt: $this->startedAt,
            completedAt: new DateTimeImmutable(),
            stepExecutions: $this->stepExecutions,
            currentExecution: $this->currentExecution,
            currentStep: $this->currentStep,
        );
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

    private function isCurrentStepRecorded(AgentStep $currentStep): bool
    {
        $lastStep = $this->stepExecutions->last()?->step;
        if ($lastStep === null) {
            return false;
        }
        return $currentStep->id() === $lastStep->id();
    }
}
