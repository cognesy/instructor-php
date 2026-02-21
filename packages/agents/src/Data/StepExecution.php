<?php declare(strict_types=1);

namespace Cognesy\Agents\Data;

use Cognesy\Agents\Continuation\ExecutionContinuation;
use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;

/**
 * Immutable wrapper bundling a step with its stop signal and timing.
 *
 * This abstraction cleanly separates step data from stop resolution,
 * avoiding the need to modify step objects after creation.
 * StepExecution identity follows AgentStep identity; the orchestrator does not overwrite step ids.
 *
 * Benefits:
 * - Step objects remain unmodified after creation
 * - Clear ownership: result bundles step + stop signal + timing explicitly
 * - Consistent pattern across all StepByStep orchestrators
 * - Natural for serialization: result is a complete unit
 */
final readonly class StepExecution
{
    private AgentStepId $id;
    private AgentStep $step;
    private ExecutionContinuation $continuation;
    private DateTimeImmutable $startedAt;
    private DateTimeImmutable $completedAt;

    public function __construct(
        AgentStep             $step,
        ExecutionContinuation $continuation,
        DateTimeImmutable     $startedAt,
        DateTimeImmutable     $completedAt,
        ?AgentStepId          $id = null,
    ) {
        $this->id = $id ?? $step->stepId();
        $this->step = $step;
        $this->continuation = $continuation;
        $this->startedAt = $startedAt;
        $this->completedAt = $completedAt;
    }

    public static function create(
        AgentStep             $step,
        ExecutionContinuation $continuation,
        DateTimeImmutable     $startedAt,
    ): self {
        return new self(
            step: $step,
            continuation: $continuation,
            startedAt: $startedAt,
            completedAt: new DateTimeImmutable(),
        );
    }

    // ACCESSORS ///////////////////////////////////////////////

    public function id(): AgentStepId {
        return $this->id;
    }

    public function step(): AgentStep {
        return $this->step;
    }

    public function continuation(): ExecutionContinuation {
        return $this->continuation;
    }

    public function startedAt(): DateTimeImmutable {
        return $this->startedAt;
    }

    public function completedAt(): DateTimeImmutable {
        return $this->completedAt;
    }

    public function duration(): float {
        $start = (float) $this->startedAt->format('U.u');
        $end = (float) $this->completedAt->format('U.u');
        return $end - $start;
    }

    public function usage() : Usage {
        return $this->step->usage();
    }

    // SERIALIZATION ///////////////////////////////////////

    public function toArray(): array {
        return [
            'id' => $this->id->value,
            'step' => $this->step->toArray(),
            'continuation' => $this->continuation->toArray(),
            'startedAt' => $this->startedAt->format(DateTimeImmutable::ATOM),
            'completedAt' => $this->completedAt->format(DateTimeImmutable::ATOM),
            'duration' => $this->duration(),
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            step: AgentStep::fromArray($data['step'] ?? []),
            continuation: ExecutionContinuation::fromArray($data['continuation'] ?? []),
            startedAt: new DateTimeImmutable($data['startedAt'] ?? 'now'),
            completedAt: new DateTimeImmutable($data['completedAt'] ?? 'now'),
            id: isset($data['id']) ? new AgentStepId($data['id']) : null,
        );
    }
}
