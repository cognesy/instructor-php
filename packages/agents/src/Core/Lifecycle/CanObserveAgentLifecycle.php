<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Lifecycle;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\Exceptions\AgentException;
use Cognesy\Polyglot\Inference\Data\ToolCall;

/**
 * Observer interface for agent lifecycle events.
 *
 * Implementations can intercept, modify, or block actions at various
 * points in the agent execution lifecycle. All methods have sensible
 * defaults in PassThroughObserver for easy selective overriding.
 */
interface CanObserveAgentLifecycle
{
    // EXECUTION LEVEL ////////////////////////////////////////

    /**
     * Called when agent execution is about to start.
     */
    public function onBeforeExecution(AgentState $state): AgentState;

    /**
     * Called when agent execution is ending normally.
     */
    public function onAfterExecution(AgentState $state): AgentState;

    /**
     * Called when agent execution has failed.
     */
    public function onError(AgentState $state, AgentException $exception): AgentState;

    // STEP LEVEL /////////////////////////////////////////////

    /**
     * Called before each step begins.
     */
    public function onBeforeStep(AgentState $state): AgentState;

    /**
     * Called after each step completes.
     */
    public function onAfterStep(AgentState $state): AgentState;

    // TOOL LEVEL /////////////////////////////////////////////

    /**
     * Called before a tool is executed.
     * Can modify the tool call or block execution.
     */
    public function onBeforeToolUse(ToolCall $toolCall, AgentState $state): ToolUseDecision;

    /**
     * Called after a tool has executed.
     * Can modify the execution result.
     */
    public function onAfterToolUse(ToolExecution $execution, AgentState $state): ToolExecution;

    // CONTINUATION ///////////////////////////////////////////
}
