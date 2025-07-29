<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Workflow;

use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\PendingComputation;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\TagMap;
use Cognesy\Utils\Result\Result;

/**
 * Orchestrates the execution of multiple pipelines in sequence.
 *
 * A Workflow composes multiple Pipeline objects into a larger processing
 * flow, handling coordination, error propagation, and computation management
 * between pipeline boundaries.
 *
 * Example:
 * ```php
 * $workflow = Workflow::empty()
 *     ->through($validationPipeline)
 *     ->when(fn($computation) => $computation->result()->isSuccess(), $processingPipeline)
 *     ->tap($loggingPipeline);
 *
 * $result = $workflow->process($data);
 * ```
 */
class Workflow
{
    /** @var WorkflowStepInterface[] */
    private array $steps = [];
    private function __construct() {}

    /**
     * Create an empty workflow ready for step composition.
     */
    public static function empty(): static {
        return new static();
    }

    /**
     * Add a pipeline that processes the computation and returns its result.
     *
     * This is the main workflow operation - the pipeline processes the current
     * computation state and its result becomes the new computation state.
     */
    public function through(Pipeline $pipeline): static {
        $this->steps[] = new ThroughStep($pipeline);
        return $this;
    }

    /**
     * Conditionally execute a pipeline based on computation state.
     *
     * The condition callable receives the current computation and should return
     * true to execute the pipeline, false to skip it.
     */
    public function when(callable $condition, Pipeline $pipeline): static {
        $this->steps[] = new ConditionalStep($condition, $pipeline);
        return $this;
    }

    /**
     * Execute a pipeline for side effects without affecting the main flow.
     *
     * The tap pipeline executes but its result is ignored. The original
     * computation continues unchanged. Useful for logging, metrics, etc.
     */
    public function tap(Pipeline $pipeline): static {
        $this->steps[] = new TapStep($pipeline);
        return $this;
    }

    /**
     * Process input data through all workflow steps.
     *
     * Returns a PendingPipelineExecution that provides access to the final
     * result value, computation with all accumulated tags, and execution state.
     */
    public function process(mixed $value = null, array $tags = []): PendingComputation {
        return new PendingComputation(function () use ($value, $tags) {
            $computation = $this->createInitialComputation($value, $tags);
            return $this->executeSteps($computation);
        });
    }

    /**
     * Execute all workflow steps in sequence.
     *
     * Stops early if any step produces a failure result, following the
     * same short-circuit behavior as Pipeline processors.
     */
    private function executeSteps(Computation $computation): Computation {
        $current = $computation;
        foreach ($this->steps as $step) {
            // Short-circuit on failure (following Pipeline behavior)
            if ($current->result()->isFailure()) {
                return $current;
            }
            $current = $step->execute($current);
        }
        return $current;
    }

    /**
     * Create initial computation from input value and tags.
     */
    private function createInitialComputation(mixed $value, array $tags = []): Computation {
        // Handle direct Computation input
        if ($value instanceof Computation) {
            return empty($tags) ? $value : $value->with(...$tags);
        }

        // Wrap value in Result and create computation
        $result = $value instanceof Result ? $value : Result::success($value);
        return new Computation($result, TagMap::create($tags));
    }

}