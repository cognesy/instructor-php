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
    public function executionStarting(AgentState $state): AgentState
    {
        return $state;
    }

    #[\Override]
    public function executionEnding(AgentState $state): AgentState
    {
        return $state;
    }

    #[\Override]
    public function executionFailed(AgentState $state, AgentException $exception): AgentState
    {
        return $state;
    }

    #[\Override]
    public function stepStarting(AgentState $state): AgentState
    {
        return $state;
    }

    #[\Override]
    public function stepEnding(AgentState $state): AgentState
    {
        return $state;
    }

    #[\Override]
    public function toolUsing(ToolCall $toolCall, AgentState $state): ToolUseDecision
    {
        return ToolUseDecision::proceed($toolCall);
    }

    #[\Override]
    public function toolUsed(ToolExecution $execution, AgentState $state): ToolExecution
    {
        return $execution;
    }

    #[\Override]
    public function stopping(AgentState $state, StopReason $reason): StopDecision
    {
        return StopDecision::allow();
    }
}
