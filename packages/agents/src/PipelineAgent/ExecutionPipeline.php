<?php declare(strict_types=1);

namespace Cognesy\Agents\PipelineAgent;

use Cognesy\Agents\Core\Data\AgentState;
use Throwable;

/**
 * Defines the execution pipeline for agent lifecycle phases.
 *
 * Each method corresponds to a phase in the agent execution lifecycle.
 * Implementations orchestrate handlers for each phase.
 */
interface ExecutionPipeline
{
    /**
     * Called once at the start of execution.
     */
    public function beforeExecution(AgentState $state, ExecutionContext $ctx): AgentState;

    /**
     * Determines if execution should continue to the next step.
     */
    public function shouldContinue(AgentState $state, ExecutionContext $ctx): bool;

    /**
     * Called before each step begins.
     */
    public function beforeStep(AgentState $state, ExecutionContext $ctx): AgentState;

    /**
     * Executes the core step (driver.useTools).
     */
    public function executeStep(AgentState $state, ExecutionContext $ctx): AgentState;

    /**
     * Called after each step completes.
     */
    public function afterStep(AgentState $state, ExecutionContext $ctx): AgentState;

    /**
     * Called once after execution completes (loop exits).
     */
    public function afterExecution(AgentState $state, ExecutionContext $ctx): AgentState;

    /**
     * Called when an error occurs during step execution.
     */
    public function onError(Throwable $error, AgentState $state, ExecutionContext $ctx): AgentState;
}
