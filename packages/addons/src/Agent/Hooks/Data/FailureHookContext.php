<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Hooks\Data;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Throwable;

/**
 * Context for failure-related hook events (AgentFailed).
 *
 * Provides access to:
 * - The exception that caused the failure
 * - The current agent state at time of failure
 *
 * AgentFailed is fired when the agent encounters an unrecoverable error.
 *
 * @example
 * function onAgentFailed(FailureHookContext $ctx): HookOutcome {
 *     $exception = $ctx->exception();
 *     $state = $ctx->state();
 *
 *     // Log the failure
 *     $this->logger->error("Agent failed: {$exception->getMessage()}", [
 *         'agentId' => $state->agentId,
 *         'step' => $state->stepCount(),
 *         'trace' => $exception->getTraceAsString(),
 *     ]);
 *
 *     // Notify monitoring systems
 *     $this->alerting->sendAlert($exception);
 *
 *     return HookOutcome::proceed();
 * }
 */
final readonly class FailureHookContext extends AbstractHookContext
{
    /**
     * @param AgentState $state The agent state at time of failure
     * @param Throwable $exception The exception that caused the failure
     * @param array<string, mixed> $metadata Additional context metadata
     */
    public function __construct(
        AgentState $state,
        private Throwable $exception,
        array $metadata = [],
    ) {
        parent::__construct($state, $metadata);
    }

    /**
     * Create a context for AgentFailed event.
     *
     * @param AgentState $state The agent state at time of failure
     * @param Throwable $exception The exception that caused the failure
     * @param array<string, mixed> $metadata Additional metadata
     */
    public static function onFailure(
        AgentState $state,
        Throwable $exception,
        array $metadata = [],
    ): self {
        return new self($state, $exception, $metadata);
    }

    #[\Override]
    public function eventType(): HookEvent
    {
        return HookEvent::AgentFailed;
    }

    /**
     * Get the exception that caused the failure.
     */
    public function exception(): Throwable
    {
        return $this->exception;
    }

    /**
     * Get the exception message.
     */
    public function errorMessage(): string
    {
        return $this->exception->getMessage();
    }

    /**
     * Get the exception class name.
     */
    public function errorClass(): string
    {
        return $this->exception::class;
    }

    #[\Override]
    public function withState(AgentState $state): static
    {
        return new self($state, $this->exception, $this->metadata);
    }

    #[\Override]
    public function withMetadata(string $key, mixed $value): static
    {
        return new self(
            $this->state,
            $this->exception,
            [...$this->metadata, $key => $value],
        );
    }
}
