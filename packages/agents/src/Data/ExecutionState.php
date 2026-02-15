<?php declare(strict_types=1);

namespace Cognesy\Agents\Data;

use Cognesy\Agents\Collections\ErrorList;
use Cognesy\Agents\Collections\StepExecutions;
use Cognesy\Agents\Continuation\ExecutionContinuation;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;
use Throwable;

/**
 * Execution-specific state that is transient across executions.
 */
final readonly class ExecutionState
{
    public function __construct(
        private string                $executionId,
        private ExecutionStatus       $status,
        private ?DateTimeImmutable    $startedAt,
        private ?DateTimeImmutable    $completedAt,
        private ?StepExecutions       $stepExecutions,
        // transient state for the current step
        private ExecutionContinuation $continuation,
        private ?DateTimeImmutable    $currentStepStartedAt,
        private ?AgentStep            $currentStep,
    ) {}

    // STATE TRANSITIONS /////////////////////////////////

    public static function fresh(): self {
        return new self(
            executionId: Uuid::uuid4(),
            status: ExecutionStatus::InProgress,
            startedAt: new DateTimeImmutable(),
            completedAt: null,
            stepExecutions: StepExecutions::empty(),
            continuation: ExecutionContinuation::fresh(),
            currentStepStartedAt: new DateTimeImmutable(),
            currentStep: null,
        );
    }

    public function withCurrentStepCompleted(?ExecutionStatus $status = null) : self {
        $stepExecutions = match(true) {
            ($this->currentStep === null) => $this->stepExecutions,
            default => $this->stepExecutions->withStepExecution(
                StepExecution::create(
                    step: $this->ensureCurrentStep(),
                    continuation: $this->continuation->withContinuationRequested(false),
                    startedAt: $this->currentStepStartedAt ?? new DateTimeImmutable(),
                ),
            ),
        };
        return new self(
            executionId: $this->executionId,
            status: $status ?? ExecutionStatus::InProgress,
            startedAt: $this->startedAt,
            completedAt: ($status !== ExecutionStatus::InProgress) ? new DateTimeImmutable() : null,
            stepExecutions: $stepExecutions,
            continuation: $this->continuation->withContinuationRequested(false),
            currentStepStartedAt: null,
            currentStep: null,
        );
    }

    public function withCurrentStepFailed() : self {
        return $this->withCurrentStepCompleted(ExecutionStatus::Failed);
    }

    public function withContinuationRequested() : self {
        return $this->with(continuation: $this->continuation->withContinuationRequested(true));
    }

    public function completed(?ExecutionStatus $status = null): self {
        $stepExecutions = match(true) {
            ($this->currentStep === null) => $this->stepExecutions,
            default => $this->stepExecutions->withStepExecution(
                StepExecution::create(
                    step: $this->ensureCurrentStep(),
                    continuation: $this->continuation->withContinuationRequested(false),
                    startedAt: $this->currentStepStartedAt ?? new DateTimeImmutable(),
                ),
            ),
        };
        return new self(
            executionId: $this->executionId,
            status: $status ?? ExecutionStatus::Completed,
            startedAt: $this->startedAt,
            completedAt: new DateTimeImmutable(),
            stepExecutions: $stepExecutions,
            continuation: $this->continuation->withContinuationRequested(false),
            currentStepStartedAt: null,
            currentStep: null,
        );
    }

    public function failed(Throwable $error): self {
        $stepExecutions = match(true) {
            ($this->currentStep === null) => $this->stepExecutions,
            default => $this->stepExecutions->withStepExecution(
                StepExecution::create(
                    step: $this->ensureCurrentStep()->withError($error),
                    continuation: $this->continuation->withContinuationRequested(false),
                    startedAt: $this->currentStepStartedAt ?? new DateTimeImmutable(),
                )
            ),
        };
        return new self(
            executionId: $this->executionId,
            status: ExecutionStatus::Failed,
            startedAt: $this->startedAt,
            completedAt: new DateTimeImmutable(),
            stepExecutions: $stepExecutions,
            continuation: ExecutionContinuation::fresh(),
            currentStepStartedAt: null,
            currentStep: null,
        );
    }

    // ACCESSORS ////////////////////////////////////////////

    public function executionId() : string {
        return $this->executionId;
    }

    public function status() : ExecutionStatus {
        return $this->status;
    }

    public function isFailed() : bool {
        return $this->status === ExecutionStatus::Failed;
    }

    public function stepExecutions() : ?StepExecutions {
        return $this->stepExecutions;
    }

    public function currentStep(): ?AgentStep {
        return $this->currentStep;
    }

    public function continuation() : ExecutionContinuation {
        return $this->continuation;
    }

    public function shouldStop() : bool {
        return match(true) {
            $this->continuation->shouldStop() => true,
            $this->continuation->isContinuationRequested() => false,
            $this->hasToolCalls() => false,
            default => true,
        };
    }

    private function hasToolCalls() : bool {
        return $this->currentStep?->hasToolCalls() ?? false;
    }

    public function stepCount(): int {
        return match(true) {
            $this->currentStep !== null => $this->stepExecutions->count() + 1,
            default => $this->stepExecutions->count(),
        };
    }

    public function usage(): Usage {
        $usage = $this->stepExecutions->totalUsage();
        $currentStep = $this->ensureCurrentStep();
        return $usage->withAccumulated($currentStep->usage());
    }

    public function stepsDuration(): float {
        return $this->stepExecutions->totalDuration();
    }

    public function totalDuration(): float {
        $endTime = $this->completedAt ?? new DateTimeImmutable();
        $startTime = $this->startedAt ?? $endTime;
        return (float) $endTime->format('U.u') - (float) $startTime->format('U.u');
    }

    public function currentStepDuration() : float {
        $endTime = new DateTimeImmutable();
        $startTime = $this->currentStepStartedAt ?? $endTime;
        return (float) $endTime->format('U.u') - (float) $startTime->format('U.u');
    }

    public function hasErrors() : bool {
        return match(true) {
            $this->currentStep?->errors()->hasAny() => true,
            $this->stepExecutions?->errors()->hasAny() => true,
            default => false,
        };
    }

    public function errors() : ErrorList {
        $errors = ErrorList::empty();
        if ($this->currentStep !== null) {
            $errors = $errors->withMergedErrorList($this->currentStep->errors());
        }
        if ($this->stepExecutions !== null) {
            $errors = $errors->withMergedErrorList($this->stepExecutions->errors());
        }
        return $errors;
    }

    // IMMUTABLE MUTATORS ///////////////////////////////////

    public function with(
        ?string                $executionId = null,
        ?ExecutionStatus       $status = null,
        ?DateTimeImmutable     $startedAt = null,
        ?DateTimeImmutable     $completedAt = null,
        ?StepExecutions        $stepExecutions = null,
        ?ExecutionContinuation $continuation = null,
        ?DateTimeImmutable     $currentStepStartedAt = null,
        ?AgentStep             $currentStep = null,
    ): self {
        return new self(
            executionId: $executionId ?? $this->executionId,
            status: $status ?? $this->status,
            startedAt: $startedAt ?? $this->startedAt,
            completedAt: $completedAt ?? $this->completedAt,
            stepExecutions: $stepExecutions ?? $this->stepExecutions,
            continuation: $continuation ?? $this->continuation,
            currentStepStartedAt: $currentStepStartedAt ?? $this->currentStepStartedAt,
            currentStep: $currentStep ?? $this->currentStep,
        );
    }

    public function withStatus(ExecutionStatus $status): self {
        return $this->with(status: $status);
    }

    public function withStopSignal(StopSignal $signal): self {
        return $this->with(continuation: $this->continuation->withNewStopSignal($signal));
    }

    public function withCurrentStep(?AgentStep $step): self {
        return $this->with(
            currentStepStartedAt: ($step !== null) ? new DateTimeImmutable() : $this->currentStepStartedAt,
            currentStep: $step,
        );
    }

    // SERIALIZATION ///////////////////////////////////////

    public function toArray(): array {
        return [
            'executionId' => $this->executionId,
            'status' => $this->status->value,
            'startedAt' => $this->startedAt->format(DateTimeImmutable::ATOM),
            'completedAt' => $this->completedAt?->format(DateTimeImmutable::ATOM),
            'stepExecutions' => $this->stepExecutions->toArray(),
            'continuation' => $this->continuation->toArray(),
            'currentStepStartedAt' => $this->currentStepStartedAt?->format(DateTimeImmutable::ATOM),
            'currentStep' => $this->currentStep?->toArray(),
        ];
    }

    public static function fromArray(array $data): self {
        $currentStep = match (true) {
            !isset($data['currentStep']) => null,
            !is_array($data['currentStep']) => null,
            $data['currentStep'] === [] => null,
            default => AgentStep::fromArray($data['currentStep']),
        };

        $status = match (true) {
            isset($data['status']) => ExecutionStatus::from($data['status']),
            default => ExecutionStatus::Pending,
        };

        return new self(
            executionId: $data['executionId'] ?? Uuid::uuid4(),
            status: $status,
            startedAt: self::makeDateTime('startedAt', $data, new DateTimeImmutable()),
            completedAt: self::makeDateTime('completedAt', $data, null),
            stepExecutions: StepExecutions::fromArray($data['stepExecutions'] ?? []),
            continuation: ExecutionContinuation::fromArray($data['continuation'] ?? []),
            currentStepStartedAt: self::makeDateTime('currentStepStartedAt', $data),
            currentStep: $currentStep,
        );
    }

    // PRIVATE HELPERS ///////////////////////////////////////

    private function ensureCurrentStep(): AgentStep {
        return $this->currentStep ?? AgentStep::empty();
    }

    private static function makeDateTime(string $key, array $data, ?DateTimeImmutable $default = null): ?DateTimeImmutable {
        $value = $data[$key] ?? null;

        if (!is_string($value) || $value === '') {
            return $default;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return $default;
        }
    }
}
