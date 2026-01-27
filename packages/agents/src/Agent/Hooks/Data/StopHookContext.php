<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Hooks\Data;

use Cognesy\Agents\Agent\Hooks\Enums\HookType;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Data\AgentState;

/**
 * Context for stop-related hook events (Stop, SubagentStop).
 *
 * Provides access to:
 * - The continuation outcome that triggered the stop
 * - The current agent state
 * - Whether this is a subagent stop
 *
 * Stop hooks can either allow the stop or block it to force continuation.
 *
 * @example
 * function onStop(StopHookContext $ctx): HookOutcome {
 *     $outcome = $ctx->continuationOutcome();
 *
 *     // Force continuation if there's unfinished work
 *     if ($this->hasUnfinishedWork($ctx->state())) {
 *         return HookOutcome::block('Work remaining - forcing continuation');
 *     }
 *
 *     // Allow the stop
 *     return HookOutcome::proceed();
 * }
 *
 * @example
 * function onSubagentStop(StopHookContext $ctx): HookOutcome {
 *     // Log subagent completion
 *     $this->logger->info("Subagent stopped: {$ctx->continuationOutcome()->stopReason()->value}");
 *     return HookOutcome::proceed();
 * }
 */
final readonly class StopHookContext extends AbstractHookContext
{
    /**
     * @param AgentState $state The current agent state
     * @param ContinuationOutcome $outcome The continuation outcome that triggered the stop
     * @param HookType $event The specific event (Stop or SubagentStop)
     * @param array<string, mixed> $metadata Additional context metadata
     */
    public function __construct(
        AgentState                  $state,
        private ContinuationOutcome $outcome,
        private HookType            $event = HookType::Stop,
        array                       $metadata = [],
    ) {
        parent::__construct($state, $metadata);
    }

    /**
     * Create a context for Stop event.
     *
     * @param AgentState $state The current agent state
     * @param ContinuationOutcome $outcome The continuation outcome
     * @param array<string, mixed> $metadata Additional metadata
     */
    public static function onStop(
        AgentState $state,
        ContinuationOutcome $outcome,
        array $metadata = [],
    ): self {
        return new self($state, $outcome, HookType::Stop, $metadata);
    }

    /**
     * Create a context for SubagentStop event.
     *
     * @param AgentState $state The current agent state
     * @param ContinuationOutcome $outcome The continuation outcome
     * @param array<string, mixed> $metadata Additional metadata
     */
    public static function onSubagentStop(
        AgentState $state,
        ContinuationOutcome $outcome,
        array $metadata = [],
    ): self {
        return new self($state, $outcome, HookType::SubagentStop, $metadata);
    }

    #[\Override]
    public function eventType(): HookType
    {
        return $this->event;
    }

    /**
     * Get the continuation outcome that triggered the stop.
     */
    public function continuationOutcome(): ContinuationOutcome
    {
        return $this->outcome;
    }

    /**
     * Check if this is a regular Stop event.
     */
    public function isStop(): bool
    {
        return $this->event === HookType::Stop;
    }

    /**
     * Check if this is a SubagentStop event.
     */
    public function isSubagentStop(): bool
    {
        return $this->event === HookType::SubagentStop;
    }

    #[\Override]
    public function withState(AgentState $state): static
    {
        return new self($state, $this->outcome, $this->event, $this->metadata);
    }

    #[\Override]
    public function withMetadata(string $key, mixed $value): static
    {
        return new self(
            $this->state,
            $this->outcome,
            $this->event,
            [...$this->metadata, $key => $value],
        );
    }
}
