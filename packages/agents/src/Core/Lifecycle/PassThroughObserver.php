<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Lifecycle;

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
    public function onBeforeExecution(AgentState $state): AgentState
    {
        return $state;
    }

    #[\Override]
    public function onAfterExecution(AgentState $state): AgentState
    {
        return $state;
    }

    #[\Override]
    public function onError(AgentState $state, AgentException $exception): AgentState
    {
        return $state;
    }

    #[\Override]
    public function onBeforeStep(AgentState $state): AgentState
    {
        return $state;
    }

    #[\Override]
    public function onAfterStep(AgentState $state): AgentState
    {
        return $state;
    }

    #[\Override]
    public function onBeforeToolUse(ToolCall $toolCall, AgentState $state): ToolUseDecision
    {
        return ToolUseDecision::proceed($toolCall);
    }

    #[\Override]
    public function onAfterToolUse(ToolExecution $execution, AgentState $state): ToolExecution
    {
        return $execution;
    }
}
