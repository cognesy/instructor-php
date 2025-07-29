<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Workflow;

use Cognesy\Pipeline\Envelope;
use Cognesy\Pipeline\PendingPipelineExecution;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\StampMap;
use Cognesy\Utils\Result\Result;

/**
 * Orchestrates the execution of multiple pipelines in sequence.
 *
 * A Workflow composes multiple Pipeline objects into a larger processing
 * flow, handling coordination, error propagation, and envelope management
 * between pipeline boundaries.
 *
 * Example:
 * ```php
 * $workflow = Workflow::empty()
 *     ->through($validationPipeline)
 *     ->when(fn($env) => $env->result()->isSuccess(), $processingPipeline)
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
     * Add a pipeline that processes the envelope and returns its result.
     *
     * This is the main workflow operation - the pipeline processes the current
     * envelope state and its result becomes the new envelope state.
     */
    public function through(Pipeline $pipeline): static {
        $this->steps[] = new ThroughStep($pipeline);
        return $this;
    }

    /**
     * Conditionally execute a pipeline based on envelope state.
     *
     * The condition callable receives the current envelope and should return
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
     * envelope continues unchanged. Useful for logging, metrics, etc.
     */
    public function tap(Pipeline $pipeline): static {
        $this->steps[] = new TapStep($pipeline);
        return $this;
    }

    /**
     * Process input data through all workflow steps.
     *
     * Returns a PendingPipelineExecution that provides access to the final
     * result value, envelope with all accumulated stamps, and execution state.
     */
    public function process(mixed $value = null, array $stamps = []): PendingPipelineExecution {
        return new PendingPipelineExecution(function () use ($value, $stamps) {
            $envelope = $this->createInitialEnvelope($value, $stamps);
            return $this->executeSteps($envelope);
        });
    }

    /**
     * Execute all workflow steps in sequence.
     *
     * Stops early if any step produces a failure result, following the
     * same short-circuit behavior as Pipeline processors.
     */
    private function executeSteps(Envelope $envelope): Envelope {
        $current = $envelope;

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
     * Create initial envelope from input value and stamps.
     */
    private function createInitialEnvelope(mixed $value, array $stamps = []): Envelope {
        // Handle direct Envelope input
        if ($value instanceof Envelope) {
            return empty($stamps) ? $value : $value->with(...$stamps);
        }

        // Wrap value in Result and create envelope
        $result = $value instanceof Result ? $value : Result::success($value);
        return new Envelope($result, StampMap::create($stamps));
    }

}