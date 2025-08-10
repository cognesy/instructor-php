<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Workflow;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Processor\ConditionalCall;
use Cognesy\Pipeline\Processor\Tap;

/**
 * Orchestrates the execution of multiple pipelines in sequence.
 *
 * A Workflow composes multiple Pipeline objects into a larger processing
 * flow, handling coordination, error propagation, and state management
 * between pipeline boundaries.
 */
class Workflow implements CanProcessState
{
    /** @var CanProcessState[] */
    private array $steps = [];
    private function __construct() {}

    public static function empty(): static {
        return new static();
    }

    public function through(CanProcessState $step): static {
        $this->steps[] = $step;
        return $this;
    }

    /**
     * @param callable(ProcessingState):bool $condition
     */
    public function when(callable $condition, CanProcessState $step): static {
        $this->steps[] = ConditionalCall::withState($condition)->then($step);
        return $this;
    }

    public function tap(CanProcessState $step): static {
        $this->steps[] = Tap::with($step);
        return $this;
    }

    public function process(ProcessingState $state): ProcessingState {
        return $this->executeSteps($state);
    }

    private function executeSteps(ProcessingState $state): ProcessingState {
        $current = $state;
        foreach ($this->steps as $step) {
            if ($current->result()->isFailure()) {
                return $current;
            }
            $current = $step->process($current);
        }
        return $current;
    }
}
