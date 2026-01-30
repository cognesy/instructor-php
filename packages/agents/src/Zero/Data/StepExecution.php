<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero\Data;

use Cognesy\Agents\Zero\Stop\StopReason;
use Cognesy\Agents\Zero\Stop\ContinuationOutcome;
use DateTimeImmutable;

/**
 * Immutable wrapper bundling a step with its continuation outcome and timing.
 *
 * This abstraction cleanly separates step data from continuation evaluation,
 * avoiding the need to modify step objects after creation.
 * StepExecution identity follows AgentStep identity; the orchestrator does not overwrite step ids.
 *
 * Benefits:
 * - Step objects remain unmodified after creation
 * - Clear ownership: result bundles step + outcome + timing explicitly
 * - Consistent pattern across all StepByStep orchestrators
 * - Natural for serialization: result is a complete unit
 */
final readonly class StepExecution
{
    public string $id;

    public function __construct(
        public AgentStep           $step,
        public ContinuationOutcome $outcome,
        public DateTimeImmutable   $startedAt,
        public DateTimeImmutable   $completedAt,
        public int                 $stepNumber,
        string                     $id = '',
    ) {
        $this->id = $id !== '' ? $id : $step->id();
    }

    /**
     * Duration of this step in seconds.
     */
    public function duration(): float {
        $start = (float) $this->startedAt->format('U.u');
        $end = (float) $this->completedAt->format('U.u');
        return $end - $start;
    }

    /**
     * Whether the orchestrator should continue after this step.
     */
    public function shouldContinue(): bool {
        return $this->outcome->shouldContinue();
    }

    /**
     * Get the reason for stopping (if applicable).
     */
    public function stopReason(): StopReason {
        return $this->outcome->stopReason();
    }

    /**
     * Serialize to array.
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'stepNumber' => $this->stepNumber,
            'step' => $this->step->toArray(),
            'outcome' => $this->outcome->toArray(),
            'startedAt' => $this->startedAt->format(DateTimeImmutable::ATOM),
            'completedAt' => $this->completedAt->format(DateTimeImmutable::ATOM),
            'duration' => $this->duration(),
        ];
    }

    /**
     * Deserialize from array.
     */
    public static function fromArray(array $data): self {
        $stepNumberValue = $data['stepNumber'] ?? $data['step_number'] ?? 1;
        $stepNumber = is_int($stepNumberValue) ? $stepNumberValue : (int) $stepNumberValue;
        $idValue = $data['id'] ?? '';
        $id = is_string($idValue) ? $idValue : '';

        return new self(
            step: AgentStep::fromArray($data['step'] ?? []),
            outcome: ContinuationOutcome::fromArray($data['outcome'] ?? []),
            startedAt: new DateTimeImmutable($data['startedAt'] ?? 'now'),
            completedAt: new DateTimeImmutable($data['completedAt'] ?? 'now'),
            stepNumber: $stepNumber,
            id: $id,
        );
    }
}
