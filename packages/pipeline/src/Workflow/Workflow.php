<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Workflow;

use Cognesy\Pipeline\CanProcessState;
use Cognesy\Pipeline\ProcessingState;

/**
 * Orchestrates the execution of multiple pipelines in sequence.
 *
 * A Workflow composes multiple Pipeline objects into a larger processing
 * flow, handling coordination, error propagation, and state management
 * between pipeline boundaries.
 *
 * Example:
 * ```php
 * $workflow = Workflow::empty()
 *     ->through($validationPipeline)
 *     ->when(fn($state) => $state->result()->isSuccess(), $processingPipeline)
 *     ->tap($loggingPipeline);
 *
 * $result = $workflow->process($data);
 * ```
 */
class Workflow implements CanProcessState
{
    /** @var CanProcessState[] */
    private array $steps = [];
    private function __construct() {}

    /**
     * Create an empty workflow ready for step composition.
     */
    public static function empty(): static {
        return new static();
    }

    /**
     * Add a pipeline that processes the state and returns its result.
     *
     * This is the main workflow operation - the pipeline processes the current
     * processing state and its result becomes the new processing state.
     */
    public function through(CanProcessState $step): static {
        $this->steps[] = new ThroughStep($step);
        return $this;
    }

    /**
     * Conditionally execute a pipeline based on processing state.
     *
     * The condition callable receives the current state and should return
     * true to execute the pipeline, false to skip it.
     */
    public function when(callable $condition, CanProcessState $step): static {
        $this->steps[] = new ConditionalStep($condition, $step);
        return $this;
    }

    /**
     * Execute a pipeline for side effects without affecting the main flow.
     *
     * The tap pipeline executes but its result is ignored. The original
     * state continues unchanged. Useful for logging, metrics, etc.
     */
    public function tap(CanProcessState $step): static {
        $this->steps[] = new TapStep($step);
        return $this;
    }

    /**
     * Process input data through all workflow steps.
     *
     * Returns a PendingPipelineExecution that provides access to the final
     * result value, state with all accumulated tags, and execution state.
     */
    public function execute(ProcessingState $state): ProcessingState {
        return $this->executeSteps($state);
    }

    /**
     * Execute all workflow steps in sequence.
     *
     * Stops early if any step produces a failure result, following the
     * same short-circuit behavior as Pipeline processors.
     */
    private function executeSteps(ProcessingState $state): ProcessingState {
        $current = $state;
        foreach ($this->steps as $step) {
            // Short-circuit on failure (following Pipeline behavior)
            if ($current->result()->isFailure()) {
                return $current;
            }
            $current = $step->execute($current);
        }
        return $current;
    }
}