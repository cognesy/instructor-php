<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Step;

use Cognesy\Addons\StepByStep\Continuation\ContinuationOutcome;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

/**
 * Immutable wrapper bundling a step with its continuation outcome.
 *
 * This abstraction cleanly separates step data from continuation evaluation,
 * avoiding the need to modify step objects after creation.
 *
 * Benefits:
 * - Step objects remain unmodified after creation
 * - Clear ownership: result bundles step + outcome explicitly
 * - Consistent pattern across all StepByStep orchestrators
 * - Natural for serialization: result is a complete unit
 */
final readonly class StepResult
{
    public function __construct(
        public object $step,
        public ContinuationOutcome $outcome,
    ) {}

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
     * @param callable(object): array $stepSerializer
     */
    public function toArray(callable $stepSerializer): array {
        return [
            'step' => $stepSerializer($this->step),
            'outcome' => $this->outcome->toArray(),
        ];
    }

    /**
     * Deserialize from array.
     * @param callable(array): object $stepDeserializer
     */
    public static function fromArray(array $data, callable $stepDeserializer): self {
        return new self(
            step: $stepDeserializer($data['step']),
            outcome: ContinuationOutcome::fromArray($data['outcome']),
        );
    }
}
