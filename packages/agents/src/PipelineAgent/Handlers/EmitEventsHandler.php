<?php declare(strict_types=1);

namespace Cognesy\Agents\PipelineAgent\Handlers;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\Core\Events\AgentEventEmitter;
use Cognesy\Agents\PipelineAgent\ExecutionContext;
use Cognesy\Agents\PipelineAgent\Phase;
use Cognesy\Agents\PipelineAgent\PhaseHandler;

/**
 * Handler that emits lifecycle events for observability.
 *
 * This handler is configured with a phase and emits the appropriate
 * event for that phase.
 */
final class EmitEventsHandler implements PhaseHandler
{
    public function __construct(
        private readonly AgentEventEmitter $emitter,
        private readonly Phase $phase,
    ) {}

    public static function forPhase(AgentEventEmitter $emitter, Phase $phase): self
    {
        return new self($emitter, $phase);
    }

    #[\Override]
    public function handle(AgentState $state, ExecutionContext $ctx): AgentState
    {
        match ($this->phase) {
            Phase::BeforeExecution => $this->emitter->executionStarted($state, count($ctx->tools->names())),
            Phase::BeforeStep => $this->emitter->stepStarted($state),
            Phase::AfterStep => $this->emitAfterStep($state),
            Phase::AfterExecution => $this->emitter->executionFinished($state),
            Phase::OnError => $this->emitOnError($state, $ctx),
            default => null,
        };

        return $state;
    }

    private function emitAfterStep(AgentState $state): void
    {
        // Emit continuation evaluated if we have step execution
        $lastStepExecution = $state->lastStepExecution();
        if ($lastStepExecution !== null) {
            $this->emitter->continuationEvaluated($state, $lastStepExecution->outcome);
        }

        $this->emitter->stateUpdated($state);
        $this->emitter->stepCompleted($state);
    }

    private function emitOnError(AgentState $state, ExecutionContext $ctx): void
    {
        $lastStepExecution = $state->lastStepExecution();
        if ($lastStepExecution !== null) {
            $this->emitter->continuationEvaluated($state, $lastStepExecution->outcome);
        }

        $this->emitter->stateUpdated($state);

        if ($state->status() === AgentStatus::Failed && $ctx->lastException !== null) {
            $this->emitter->executionFailed($state, $ctx->lastException);
        }
    }
}
