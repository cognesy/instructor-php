<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Hooks\Enums;

/**
 * Enumeration of all hook lifecycle events in the agent system.
 *
 * Hook events represent specific points in the agent execution lifecycle
 * where custom behavior can be injected. Events are organized by category:
 *
 * Tool Lifecycle:
 * - PreToolUse: Before a tool is executed (can modify args or block)
 * - PostToolUse: After a tool completes (can modify result)
 *
 * Step Lifecycle:
 * - BeforeStep: Before each agent step begins
 * - AfterStep: After each agent step completes
 *
 * Execution Lifecycle:
 * - ExecutionStart: When agent execution begins
 * - ExecutionEnd: When agent execution completes
 *
 * Continuation:
 * - Stop: When agent is about to stop (can force continuation)
 * - SubagentStop: When a subagent is about to stop
 *
 * Error Handling:
 * - AgentFailed: When agent encounters an unrecoverable error
 */
enum HookType: string
{
    // Tool lifecycle
    case PreToolUse = 'pre_tool_use';
    case PostToolUse = 'post_tool_use';

    // Step lifecycle
    case BeforeStep = 'before_step';
    case AfterStep = 'after_step';

    // Execution lifecycle
    case ExecutionStart = 'execution_start';
    case ExecutionEnd = 'execution_end';

    // Continuation
    case Stop = 'stop';
    case SubagentStop = 'subagent_stop';

    // Error handling
    case AgentFailed = 'agent_failed';

    /**
     * Check if this is a tool-related event.
     */
    public function isToolEvent(): bool
    {
        return match ($this) {
            self::PreToolUse, self::PostToolUse => true,
            default => false,
        };
    }

    /**
     * Check if this is a step-related event.
     */
    public function isStepEvent(): bool
    {
        return match ($this) {
            self::BeforeStep, self::AfterStep => true,
            default => false,
        };
    }

    /**
     * Check if this is an execution-related event.
     */
    public function isExecutionEvent(): bool
    {
        return match ($this) {
            self::ExecutionStart, self::ExecutionEnd => true,
            default => false,
        };
    }

    /**
     * Check if this is a continuation-related event.
     */
    public function isContinuationEvent(): bool
    {
        return match ($this) {
            self::Stop, self::SubagentStop => true,
            default => false,
        };
    }

    /**
     * Check if this event supports blocking (returning HookOutcome::block()).
     */
    public function supportsBlocking(): bool
    {
        return match ($this) {
            self::PreToolUse, self::Stop, self::SubagentStop => true,
            default => false,
        };
    }

    /**
     * Check if this event supports stopping execution (returning HookOutcome::stop()).
     */
    public function supportsStopping(): bool
    {
        return match ($this) {
            self::PreToolUse, self::PostToolUse, self::BeforeStep, self::AfterStep, self::Stop, self::SubagentStop => true,
            default => false,
        };
    }
}
