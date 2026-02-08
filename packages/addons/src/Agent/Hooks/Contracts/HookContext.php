<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/instructor-agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Hooks\Contracts;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Hooks\Data\HookEvent;

/**
 * Base interface for all hook contexts.
 *
 * A HookContext provides event-specific data to hooks during execution.
 * Each hook event type has its own context implementation that exposes
 * the relevant data for that particular lifecycle point.
 *
 * All contexts provide access to:
 * - The current agent state
 * - The type of event being processed
 *
 * Specialized contexts add event-specific data like:
 * - ToolHookContext: The tool call being executed
 * - StepHookContext: The current step index
 * - StopHookContext: The continuation outcome that triggered the stop
 * - ExecutionHookContext: Execution timing information
 *
 * @example
 * function processHook(HookContext $context): HookOutcome {
 *     $state = $context->state();
 *     $event = $context->eventType();
 *
 *     // Handle based on event type
 *     return HookOutcome::proceed();
 * }
 */
interface HookContext
{
    /**
     * Get the current agent state.
     *
     * @return AgentState The current state of the agent
     */
    public function state(): AgentState;

    /**
     * Get the type of event this context represents.
     *
     * @return HookEvent The event type
     */
    public function eventType(): HookEvent;

    /**
     * Create a new context with modified state.
     *
     * This method allows hooks to pass modified state down the chain
     * while maintaining immutability of the original context.
     *
     * @param AgentState $state The modified state
     * @return static A new context instance with the modified state
     */
    public function withState(AgentState $state): static;

    /**
     * Get metadata associated with this context.
     *
     * Metadata can be used to pass information between hooks in the chain.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;

    /**
     * Create a new context with additional metadata.
     *
     * @param string $key The metadata key
     * @param mixed $value The metadata value
     * @return static A new context instance with the added metadata
     */
    public function withMetadata(string $key, mixed $value): static;
}
