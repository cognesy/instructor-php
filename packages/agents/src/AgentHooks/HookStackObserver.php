<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks;

use Cognesy\Agents\AgentHooks\Data\ExecutionHookContext;
use Cognesy\Agents\AgentHooks\Data\FailureHookContext;
use Cognesy\Agents\AgentHooks\Data\HookOutcome;
use Cognesy\Agents\AgentHooks\Data\StepHookContext;
use Cognesy\Agents\AgentHooks\Data\StopHookContext;
use Cognesy\Agents\AgentHooks\Data\ToolHookContext;
use Cognesy\Agents\AgentHooks\Stack\HookStack;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Contracts\CanEmitAgentEvents;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\Exceptions\AgentException;
use Cognesy\Agents\Core\Lifecycle\CanObserveAgentLifecycle;
use Cognesy\Agents\Core\Lifecycle\StopDecision;
use Cognesy\Agents\Core\Lifecycle\ToolUseDecision;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use DateTimeImmutable;

/**
 * Adapter that wraps HookStack as a CanObserveAgentLifecycle implementation.
 *
 * Bridges the existing hook system to the new unified lifecycle observer interface.
 */
final class HookStackObserver implements CanObserveAgentLifecycle
{
    public function __construct(
        private readonly HookStack $hookStack,
        private readonly ?CanEmitAgentEvents $eventEmitter = null,
    ) {}

    // EXECUTION LEVEL ////////////////////////////////////////

    #[\Override]
    public function onBeforeExecution(AgentState $state): AgentState
    {
        $hookStartedAt = new DateTimeImmutable();
        $context = ExecutionHookContext::onStart($state);

        $outcome = $this->hookStack->process(
            $context,
            static fn($ctx) => HookOutcome::proceed($ctx)
        );

        $this->emitLifecycleHookEvent('executionStarting', $outcome, $hookStartedAt);

        return $this->extractState($outcome, $state);
    }

    #[\Override]
    public function onAfterExecution(AgentState $state): AgentState
    {
        $hookStartedAt = new DateTimeImmutable();
        $context = ExecutionHookContext::onEnd($state);

        $outcome = $this->hookStack->process(
            $context,
            static fn($ctx) => HookOutcome::proceed($ctx)
        );

        $this->emitLifecycleHookEvent('executionEnding', $outcome, $hookStartedAt);

        return $this->extractState($outcome, $state);
    }

    #[\Override]
    public function onError(AgentState $state, AgentException $exception): AgentState
    {
        $hookStartedAt = new DateTimeImmutable();
        $context = FailureHookContext::onFailure($state, $exception);

        $outcome = $this->hookStack->process(
            $context,
            static fn($ctx) => HookOutcome::proceed($ctx)
        );

        $this->emitLifecycleHookEvent('executionFailed', $outcome, $hookStartedAt);

        return $this->extractState($outcome, $state);
    }

    // STEP LEVEL /////////////////////////////////////////////

    #[\Override]
    public function onBeforeStep(AgentState $state): AgentState
    {
        $hookStartedAt = new DateTimeImmutable();
        $stepIndex = $state->stepCount();
        $context = StepHookContext::beforeStep($state, $stepIndex);

        $outcome = $this->hookStack->process(
            $context,
            static fn($ctx) => HookOutcome::proceed($ctx)
        );

        $this->emitLifecycleHookEvent('stepStarting', $outcome, $hookStartedAt);

        return $this->extractState($outcome, $state);
    }

    #[\Override]
    public function onAfterStep(AgentState $state): AgentState
    {
        $hookStartedAt = new DateTimeImmutable();
        $stepIndex = $state->stepCount() - 1;
        $lastStep = $state->steps()->lastStep();

        // If there's no step (shouldn't happen, but be defensive), just return unchanged
        if ($lastStep === null) {
            return $state;
        }

        $context = StepHookContext::afterStep($state, $stepIndex, $lastStep);

        $outcome = $this->hookStack->process(
            $context,
            static fn($ctx) => HookOutcome::proceed($ctx)
        );

        $this->emitLifecycleHookEvent('stepEnding', $outcome, $hookStartedAt);

        return $this->extractState($outcome, $state);
    }

    // TOOL LEVEL /////////////////////////////////////////////

    #[\Override]
    public function onBeforeToolUse(ToolCall $toolCall, AgentState $state): ToolUseDecision
    {
        $hookStartedAt = new DateTimeImmutable();
        $context = ToolHookContext::beforeTool($toolCall, $state);

        $outcome = $this->hookStack->process(
            $context,
            static fn($ctx) => HookOutcome::proceed($ctx)
        );

        $this->emitToolHookEvent(
            hookType: 'beforeToolUse',
            tool: $toolCall->name(),
            outcome: $outcome,
            startedAt: $hookStartedAt,
        );

        if ($outcome->isBlocked() || $outcome->isStopped()) {
            $reason = $outcome->reason() ?? 'Blocked by hook';
            return ToolUseDecision::block($reason);
        }

        $resultToolCall = $this->extractToolCall($outcome, $toolCall);
        return ToolUseDecision::proceed($resultToolCall);
    }

    #[\Override]
    public function onAfterToolUse(ToolExecution $execution, AgentState $state): ToolExecution
    {
        $hookStartedAt = new DateTimeImmutable();
        $context = ToolHookContext::afterTool($execution->toolCall(), $execution, $state);

        $outcome = $this->hookStack->process(
            $context,
            static fn($ctx) => HookOutcome::proceed($ctx)
        );

        $this->emitToolHookEvent(
            hookType: 'afterToolUse',
            tool: $execution->toolCall()->name(),
            outcome: $outcome,
            startedAt: $hookStartedAt,
        );

        return $this->extractExecution($outcome, $execution);
    }

    // CONTINUATION ///////////////////////////////////////////

    #[\Override]
    public function onBeforeStopDecision(AgentState $state, StopReason $reason): StopDecision
    {
        $hookStartedAt = new DateTimeImmutable();

        // Create a ContinuationOutcome representing the stop decision
        $evaluation = ContinuationEvaluation::fromDecision(
            criterionClass: self::class,
            decision: ContinuationDecision::AllowStop,
            stopReason: $reason,
        );
        $outcome = new ContinuationOutcome(
            shouldContinue: false,
            evaluations: [$evaluation],
        );

        $context = StopHookContext::onStop($state, $outcome);

        $hookOutcome = $this->hookStack->process(
            $context,
            static fn($ctx) => HookOutcome::proceed($ctx)
        );

        $this->emitLifecycleHookEvent('stopping', $hookOutcome, $hookStartedAt);

        if ($hookOutcome->isBlocked()) {
            return StopDecision::prevent($hookOutcome->reason() ?? 'Blocked by hook');
        }

        return StopDecision::allow();
    }

    // ACCESSORS /////////////////////////////////////////////

    public function hookStack(): HookStack
    {
        return $this->hookStack;
    }

    // INTERNAL /////////////////////////////////////////////

    private function extractState(HookOutcome $outcome, AgentState $default): AgentState
    {
        $context = $outcome->context();
        return $context?->state() ?? $default;
    }

    private function extractToolCall(HookOutcome $outcome, ToolCall $default): ToolCall
    {
        $context = $outcome->context();
        return ($context instanceof ToolHookContext)
            ? $context->toolCall()
            : $default;
    }

    private function extractExecution(HookOutcome $outcome, ToolExecution $default): ToolExecution
    {
        $context = $outcome->context();
        return ($context instanceof ToolHookContext && $context->execution() !== null)
            ? $context->execution()
            : $default;
    }

    private function emitLifecycleHookEvent(
        string $hookType,
        HookOutcome $outcome,
        DateTimeImmutable $startedAt,
    ): void {
        if ($this->eventEmitter === null) {
            return;
        }

        $this->eventEmitter->hookExecuted(
            hookType: $hookType,
            tool: '',
            outcome: $outcome->isBlocked() ? 'blocked' : ($outcome->isStopped() ? 'stopped' : 'proceed'),
            reason: $outcome->reason(),
            startedAt: $startedAt,
        );
    }

    private function emitToolHookEvent(
        string $hookType,
        string $tool,
        HookOutcome $outcome,
        DateTimeImmutable $startedAt,
    ): void {
        if ($this->eventEmitter === null) {
            return;
        }

        $this->eventEmitter->hookExecuted(
            hookType: $hookType,
            tool: $tool,
            outcome: $outcome->isBlocked() ? 'blocked' : ($outcome->isStopped() ? 'stopped' : 'proceed'),
            reason: $outcome->reason(),
            startedAt: $startedAt,
        );
    }
}
