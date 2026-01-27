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
    public function executionStarting(AgentState $state): AgentState;

    /**
     * Called when agent execution is ending normally.
     */
    public function executionEnding(AgentState $state): AgentState;

    /**
     * Called when agent execution has failed.
     */
    public function executionFailed(AgentState $state, AgentException $exception): AgentState;

    // STEP LEVEL /////////////////////////////////////////////

    /**
     * Called before each step begins.
     */
    public function stepStarting(AgentState $state): AgentState;

    /**
     * Called after each step completes.
     */
    public function stepEnding(AgentState $state): AgentState;

    // TOOL LEVEL /////////////////////////////////////////////

    /**
     * Called before a tool is executed.
     * Can modify the tool call or block execution.
     */
    public function toolUsing(ToolCall $toolCall, AgentState $state): ToolUseDecision;

    /**
     * Called after a tool has executed.
     * Can modify the execution result.
     */
    public function toolUsed(ToolExecution $execution, AgentState $state): ToolExecution;

    // CONTINUATION ///////////////////////////////////////////

    /**
     * Called when agent is about to stop.
     * Can prevent stopping to force continuation.
     */
    public function stopping(AgentState $state, StopReason $reason): StopDecision;
}
