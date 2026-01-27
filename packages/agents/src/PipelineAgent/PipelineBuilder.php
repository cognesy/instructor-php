<?php declare(strict_types=1);

namespace Cognesy\Agents\PipelineAgent;

use Cognesy\Agents\Core\Continuation\ContinuationCriteria;
use Cognesy\Agents\Core\Contracts\CanHandleAgentErrors;
use Cognesy\Agents\Core\ErrorHandling\AgentErrorHandler;
use Cognesy\Agents\Core\Events\AgentEventEmitter;
use Cognesy\Agents\PipelineAgent\Handlers\EmitEventsHandler;
use Cognesy\Agents\PipelineAgent\Handlers\EvaluateContinuationHandler;
use Cognesy\Agents\PipelineAgent\Handlers\FinalizeExecutionHandler;
use Cognesy\Agents\PipelineAgent\Handlers\UseToolsHandler;

/**
 * Builder for creating execution pipelines with common configurations.
 *
 * Provides factory methods for common pipeline setups:
 * - minimal(): Just the core handlers needed for execution
 * - withEvents(): Adds event emission handlers
 * - withHooks(): Adds hook processing handlers
 */
final class PipelineBuilder
{
    private ComposablePipeline $pipeline;

    private function __construct(ComposablePipeline $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    /**
     * Create a minimal pipeline with only core handlers.
     *
     * This is the bare minimum needed for the agent to function:
     * - UseToolsHandler (execute_step)
     * - EvaluateContinuationHandler (after_step)
     * - FinalizeExecutionHandler (after_execution)
     */
    public static function minimal(
        ContinuationCriteria $criteria,
        ?CanHandleAgentErrors $errorHandler = null,
    ): self {
        $errorHandler = $errorHandler ?? AgentErrorHandler::default();

        $pipeline = new ComposablePipeline($criteria, $errorHandler);

        $pipeline = $pipeline
            ->addHandler(Phase::ExecuteStep, new UseToolsHandler())
            ->addHandler(Phase::AfterStep, new EvaluateContinuationHandler($criteria))
            ->addHandler(Phase::AfterExecution, new FinalizeExecutionHandler());

        return new self($pipeline);
    }

    /**
     * Add a handler to a specific phase.
     */
    public function addHandler(Phase $phase, PhaseHandler $handler): self
    {
        $this->pipeline = $this->pipeline->addHandler($phase, $handler);
        return $this;
    }

    /**
     * Add a handler to run before execution starts.
     */
    public function beforeExecution(PhaseHandler $handler): self
    {
        return $this->addHandler(Phase::BeforeExecution, $handler);
    }

    /**
     * Add a handler to run before each step.
     */
    public function beforeStep(PhaseHandler $handler): self
    {
        return $this->addHandler(Phase::BeforeStep, $handler);
    }

    /**
     * Add a handler to run after each step.
     */
    public function afterStep(PhaseHandler $handler): self
    {
        return $this->addHandler(Phase::AfterStep, $handler);
    }

    /**
     * Add a handler to run after execution completes.
     */
    public function afterExecution(PhaseHandler $handler): self
    {
        return $this->addHandler(Phase::AfterExecution, $handler);
    }

    /**
     * Add a handler to run on errors.
     */
    public function onError(PhaseHandler $handler): self
    {
        return $this->addHandler(Phase::OnError, $handler);
    }

    /**
     * Add event emission handlers for all phases.
     *
     * This enables observability by emitting events at each lifecycle phase.
     */
    public function withEvents(AgentEventEmitter $emitter): self
    {
        $this->pipeline = $this->pipeline
            ->addHandler(Phase::BeforeExecution, EmitEventsHandler::forPhase($emitter, Phase::BeforeExecution))
            ->addHandler(Phase::BeforeStep, EmitEventsHandler::forPhase($emitter, Phase::BeforeStep))
            ->addHandler(Phase::AfterStep, EmitEventsHandler::forPhase($emitter, Phase::AfterStep))
            ->addHandler(Phase::AfterExecution, EmitEventsHandler::forPhase($emitter, Phase::AfterExecution))
            ->addHandler(Phase::OnError, EmitEventsHandler::forPhase($emitter, Phase::OnError));
        return $this;
    }

    /**
     * Build the pipeline.
     */
    public function build(): ExecutionPipeline
    {
        return $this->pipeline;
    }
}
