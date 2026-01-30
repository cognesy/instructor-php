<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks;

use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\AgentHooks\Stack\HookStack;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Contracts\CanEmitAgentEvents;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\HookContext;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\Exceptions\AgentException;
use Cognesy\Agents\Core\Lifecycle\CanObserveInference;
use Cognesy\Agents\Core\Lifecycle\CanObserveAgentLifecycle;
use Cognesy\Agents\Core\Lifecycle\ToolUseDecision;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use DateTimeImmutable;

/**
 * Adapter that wraps HookStack as a CanObserveAgentLifecycle implementation.
 *
 * Bridges the hook system to the lifecycle observer interface.
 *
 * All hooks use the evaluation-based flow control model:
 * - Hooks receive AgentState directly
 * - Hooks write evaluations to state for flow control
 * - Observer extracts decisions from evaluations after hook processing
 *
 * State tracking: The observer tracks the latest state after each hook call.
 * Use state() accessor to retrieve state changes after tool hooks (which return
 * domain objects instead of AgentState).
 */
final class HookStackObserver implements CanObserveAgentLifecycle, CanObserveInference
{
    private ?AgentState $lastState = null;

    public function __construct(
        private readonly HookStack $hookStack,
        private readonly ?CanEmitAgentEvents $eventEmitter = null,
    ) {}

    // EXECUTION LEVEL ////////////////////////////////////////

    #[\Override]
    public function onBeforeExecution(AgentState $state): AgentState
    {
        $hookStartedAt = new DateTimeImmutable();

        // Notify guard hooks (e.g., ExecutionTimeLimitGuard) that execution started
        $this->hookStack->executionStarted($hookStartedAt);

        // Set event context for hooks
        $state = $this->prepareStateForEvent($state);

        $result = $this->hookStack->process($state, HookType::ExecutionStart);

        $this->emitHookEvent('executionStarting', '', $result, $hookStartedAt);

        $this->lastState = $result;
        return $this->lastState;
    }

    #[\Override]
    public function onAfterExecution(AgentState $state): AgentState
    {
        $hookStartedAt = new DateTimeImmutable();

        $state = $this->prepareStateForEvent($state);

        $result = $this->hookStack->process($state, HookType::ExecutionEnd);

        $this->emitHookEvent('executionEnding', '', $result, $hookStartedAt);

        $this->lastState = $result;
        return $this->lastState;
    }

    #[\Override]
    public function onError(AgentState $state, AgentException $exception): AgentState
    {
        $hookStartedAt = new DateTimeImmutable();

        $state = $this->prepareStateForEvent($state);

        $result = $this->hookStack->process($state, HookType::OnError);

        $this->emitHookEvent('executionFailed', '', $result, $hookStartedAt);

        $this->lastState = $result;
        return $this->lastState;
    }

    // STEP LEVEL /////////////////////////////////////////////

    #[\Override]
    public function onBeforeStep(AgentState $state): AgentState
    {
        $hookStartedAt = new DateTimeImmutable();

        $state = $this->prepareStateForEvent($state);

        $result = $this->hookStack->process($state, HookType::BeforeStep);

        $this->emitHookEvent('stepStarting', '', $result, $hookStartedAt);

        $this->lastState = $result;
        return $this->lastState;
    }

    #[\Override]
    public function onAfterStep(AgentState $state): AgentState
    {
        $hookStartedAt = new DateTimeImmutable();

        $result = $this->hookStack->process($state, HookType::AfterStep);

        $this->emitHookEvent('stepEnding', '', $result, $hookStartedAt);

        $this->lastState = $result;
        return $this->lastState;
    }

    // INFERENCE LEVEL ////////////////////////////////////////

    #[\Override]
    public function onBeforeInference(AgentState $state, Messages $messages): AgentState
    {
        $hookStartedAt = new DateTimeImmutable();

        $state = $this->prepareStateForEvent($state);

        $context = $state->hookContext() ?? HookContext::empty();
        $state = $state->withHookContext($context->withInferenceMessages($messages));

        $result = $this->hookStack->process($state, HookType::BeforeInference);

        $this->emitHookEvent('beforeInference', '', $result, $hookStartedAt);

        $this->lastState = $result;
        return $this->lastState;
    }

    #[\Override]
    public function onAfterInference(AgentState $state, InferenceResponse $response): AgentState
    {
        $hookStartedAt = new DateTimeImmutable();

        $state = $this->prepareStateForEvent($state);

        $context = $state->hookContext() ?? HookContext::empty();
        $state = $state->withHookContext($context->withInferenceResponse($response));

        $result = $this->hookStack->process($state, HookType::AfterInference);

        $this->emitHookEvent('afterInference', '', $result, $hookStartedAt);

        $this->lastState = $result;
        return $this->lastState;
    }

    // TOOL LEVEL /////////////////////////////////////////////

    #[\Override]
    public function onBeforeToolUse(ToolCall $toolCall, AgentState $state): ToolUseDecision
    {
        $hookStartedAt = new DateTimeImmutable();

        // Set tool call context for PreToolUse hooks
        $context = $state->hookContext() ?? HookContext::empty();
        $state = $state->withHookContext($context->withToolCall($toolCall));

        $result = $this->hookStack->process($state, HookType::PreToolUse);

        $this->emitHookEvent('beforeToolUse', $toolCall->name(), $result, $hookStartedAt);

        // Track state changes from tool hooks
        $this->lastState = $result;

        // Check evaluations for tool blocking
        if ($this->hasBlockingEvaluation($result)) {
            $reason = $this->getBlockingReason($result) ?? 'Blocked by hook';
            return ToolUseDecision::block($reason);
        }

        // Extract potentially modified tool call from context
        $resultToolCall = $result->hookContext()?->toolCall ?? $toolCall;
        return ToolUseDecision::proceed($resultToolCall);
    }

    #[\Override]
    public function onAfterToolUse(ToolExecution $execution, AgentState $state): ToolExecution
    {
        $hookStartedAt = new DateTimeImmutable();

        // Set tool execution context for PostToolUse hooks
        $context = $state->hookContext() ?? HookContext::empty();
        $state = $state->withHookContext(
            $context
                ->withToolCall($execution->toolCall())
                ->withToolExecution($execution)
        );

        $result = $this->hookStack->process($state, HookType::PostToolUse);

        $this->emitHookEvent('afterToolUse', $execution->toolCall()->name(), $result, $hookStartedAt);

        // Track state changes from tool hooks
        $this->lastState = $result;

        // Extract potentially modified tool execution from context
        return $result->hookContext()?->toolExecution ?? $execution;
    }

    // ACCESSORS /////////////////////////////////////////////

    public function hookStack(): HookStack
    {
        return $this->hookStack;
    }

    /**
     * Get the last state processed by any hook.
     *
     * Use this after tool hooks (onBeforeToolUse, onAfterToolUse) to retrieve
     * any state changes made by hooks, since those methods return domain objects
     * instead of AgentState.
     */
    public function state(): ?AgentState
    {
        return $this->lastState;
    }

    // INTERNAL /////////////////////////////////////////////

    /**
     * Prepare state for hook processing by ensuring CurrentExecution exists.
     */
    private function prepareStateForEvent(AgentState $state): AgentState
    {
        if ($state->currentExecution() === null) {
            $state = $state->withNewStepExecution();
        }
        if ($state->hookContext() === null) {
            $state = $state->withHookContext(HookContext::empty());
        }
        return $state;
    }

    /**
     * Check if any evaluation forbids continuation (used for tool blocking).
     */
    private function hasBlockingEvaluation(AgentState $state): bool
    {
        $evaluations = $state->evaluations();

        foreach ($evaluations as $evaluation) {
            if ($evaluation->decision === ContinuationDecision::ForbidContinuation) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the reason from the first blocking evaluation.
     */
    private function getBlockingReason(AgentState $state): ?string
    {
        $evaluations = $state->evaluations();

        foreach ($evaluations as $evaluation) {
            if ($evaluation->decision === ContinuationDecision::ForbidContinuation) {
                return $evaluation->reason;
            }
        }

        return null;
    }

    /**
     * Determine outcome string from state evaluations for event emission.
     */
    private function determineOutcome(AgentState $state): string
    {
        $evaluations = $state->evaluations();

        foreach ($evaluations as $evaluation) {
            if ($evaluation->decision === ContinuationDecision::ForbidContinuation) {
                return 'blocked';
            }
        }

        return 'proceed';
    }

    private function emitHookEvent(
        string $hookType,
        string $tool,
        AgentState $result,
        DateTimeImmutable $startedAt,
    ): void {
        if ($this->eventEmitter === null) {
            return;
        }

        $outcome = $this->determineOutcome($result);
        $reason = $this->getBlockingReason($result);

        $this->eventEmitter->hookExecuted(
            hookType: $hookType,
            tool: $tool,
            outcome: $outcome,
            reason: $reason,
            startedAt: $startedAt,
        );
    }
}
