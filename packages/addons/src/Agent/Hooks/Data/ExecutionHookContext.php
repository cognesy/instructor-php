<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Hooks\Data;

use Cognesy\Addons\Agent\Core\Data\AgentState;

/**
 * Context for execution-related hook events (ExecutionStart, ExecutionEnd).
 *
 * Provides access to:
 * - The current agent state
 * - Execution timing information
 *
 * ExecutionStart is fired when agent.run() begins.
 * ExecutionEnd is fired when agent.run() completes (success or failure).
 *
 * @example
 * function onExecutionStart(ExecutionHookContext $ctx): HookOutcome {
 *     // Initialize monitoring
 *     $this->metrics->startTracking($ctx->state()->agentId);
 *
 *     return HookOutcome::proceed(
 *         $ctx->withMetadata('execution_started', microtime(true))
 *     );
 * }
 *
 * @example
 * function onExecutionEnd(ExecutionHookContext $ctx): HookOutcome {
 *     // Finalize monitoring
 *     $duration = microtime(true) - $ctx->get('execution_started', 0);
 *     $this->metrics->recordExecution($ctx->state()->agentId, $duration);
 *
 *     return HookOutcome::proceed();
 * }
 */
final readonly class ExecutionHookContext extends AbstractHookContext
{
    /**
     * @param AgentState $state The current agent state
     * @param HookEvent $event The specific event (ExecutionStart or ExecutionEnd)
     * @param array<string, mixed> $metadata Additional context metadata
     */
    public function __construct(
        AgentState $state,
        private HookEvent $event = HookEvent::ExecutionStart,
        array $metadata = [],
    ) {
        parent::__construct($state, $metadata);
    }

    /**
     * Create a context for ExecutionStart event.
     *
     * @param AgentState $state The current agent state
     * @param array<string, mixed> $metadata Additional metadata
     */
    public static function onStart(AgentState $state, array $metadata = []): self
    {
        return new self($state, HookEvent::ExecutionStart, $metadata);
    }

    /**
     * Create a context for ExecutionEnd event.
     *
     * @param AgentState $state The final agent state
     * @param array<string, mixed> $metadata Additional metadata
     */
    public static function onEnd(AgentState $state, array $metadata = []): self
    {
        return new self($state, HookEvent::ExecutionEnd, $metadata);
    }

    #[\Override]
    public function eventType(): HookEvent
    {
        return $this->event;
    }

    /**
     * Check if this is an ExecutionStart event.
     */
    public function isStart(): bool
    {
        return $this->event === HookEvent::ExecutionStart;
    }

    /**
     * Check if this is an ExecutionEnd event.
     */
    public function isEnd(): bool
    {
        return $this->event === HookEvent::ExecutionEnd;
    }

    #[\Override]
    public function withState(AgentState $state): static
    {
        return new self($state, $this->event, $this->metadata);
    }

    #[\Override]
    public function withMetadata(string $key, mixed $value): static
    {
        return new self(
            $this->state,
            $this->event,
            [...$this->metadata, $key => $value],
        );
    }
}
