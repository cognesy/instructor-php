<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Enums;

/**
 * Enumeration of all hook lifecycle events in the agent system.
 *
 * Hook events represent specific points in the agent execution lifecycle
 * where custom behavior can be injected. Hooks influence control flow by
 * writing continuation evaluations into AgentState; decisions are resolved
 * after each step from aggregated evaluations.
 *
 * Tool Lifecycle:
 * - PreToolUse: Before a tool is executed (can modify args or block)
 * - PostToolUse: After a tool completes (can modify result)
 *
 * Inference Lifecycle:
 * - BeforeInference: Before LLM inference (can modify messages)
 * - AfterInference: After LLM inference (can modify response)
 *
 * Step Lifecycle:
 * - BeforeStep: Before each agent step begins
 * - AfterStep: After each agent step completes
 *
 * Execution Lifecycle:
 * - ExecutionStart: When agent execution begins
 * - ExecutionEnd: When agent execution completes
 *
 * Error Handling:
 * - OnError: When an error occurs during execution (can recover or transform)
 */
enum HookType: string
{
    // Tool lifecycle
    case PreToolUse = 'pre_tool_use';
    case PostToolUse = 'post_tool_use';

    // Inference lifecycle
    case BeforeInference = 'before_inference';
    case AfterInference = 'after_inference';

    // Step lifecycle
    case BeforeStep = 'before_step';
    case AfterStep = 'after_step';

    // Execution lifecycle
    case ExecutionStart = 'execution_start';
    case ExecutionEnd = 'execution_end';

    // Error handling
    case OnError = 'on_error';

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
     * Check if this is an inference-related event.
     */
    public function isInferenceEvent(): bool
    {
        return match ($this) {
            self::BeforeInference, self::AfterInference => true,
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
     * Check if this is an error-related event.
     */
    public function isErrorEvent(): bool
    {
        return match ($this) {
            self::OnError => true,
            default => false,
        };
    }
}
