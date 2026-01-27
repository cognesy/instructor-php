<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Lifecycle;

use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\Exceptions\AgentException;
use Cognesy\Polyglot\Inference\Data\ToolCall;

/**
 * Default pass-through implementation of lifecycle observer.
 *
 * All methods pass through unchanged. Extend this class to override
 * only the methods you care about.
 */
class PassThroughObserver implements CanObserveAgentLifecycle
{
    #[\Override]
    public function beforeExecution(AgentState $state): AgentState
    {
        return $state;
    }

    #[\Override]
    public function afterExecution(AgentState $state): AgentState
    {
        return $state;
    }

    #[\Override]
    public function onError(AgentState $state, AgentException $exception): AgentState
    {
        return $state;
    }

    #[\Override]
    public function beforeStep(AgentState $state): AgentState
    {
        return $state;
    }

    #[\Override]
    public function afterStep(AgentState $state): AgentState
    {
        return $state;
    }

    #[\Override]
    public function beforeToolUse(ToolCall $toolCall, AgentState $state): ToolUseDecision
    {
        return ToolUseDecision::proceed($toolCall);
    }

    #[\Override]
    public function afterToolUse(ToolExecution $execution, AgentState $state): ToolExecution
    {
        return $execution;
    }

    #[\Override]
    public function beforeStopDecision(AgentState $state, StopReason $reason): StopDecision
    {
        return StopDecision::allow();
    }
}
