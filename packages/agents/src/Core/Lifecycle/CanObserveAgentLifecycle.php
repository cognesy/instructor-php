<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Lifecycle;

use Cognesy\Agents\Core\Continuation\Enums\StopReason;
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
    public function beforeExecution(AgentState $state): AgentState;

    /**
     * Called when agent execution is ending normally.
     */
    public function afterExecution(AgentState $state): AgentState;

    /**
     * Called when agent execution has failed.
     */
    public function onError(AgentState $state, AgentException $exception): AgentState;

    // STEP LEVEL /////////////////////////////////////////////

    /**
     * Called before each step begins.
     */
    public function beforeStep(AgentState $state): AgentState;

    /**
     * Called after each step completes.
     */
    public function afterStep(AgentState $state): AgentState;

    // TOOL LEVEL /////////////////////////////////////////////

    /**
     * Called before a tool is executed.
     * Can modify the tool call or block execution.
     */
    public function beforeToolUse(ToolCall $toolCall, AgentState $state): ToolUseDecision;

    /**
     * Called after a tool has executed.
     * Can modify the execution result.
     */
    public function afterToolUse(ToolExecution $execution, AgentState $state): ToolExecution;

    // CONTINUATION ///////////////////////////////////////////

    /**
     * Called when agent is about to stop.
     * Can prevent stopping to force continuation.
     */
    public function beforeStopDecision(AgentState $state, StopReason $reason): StopDecision;
}
