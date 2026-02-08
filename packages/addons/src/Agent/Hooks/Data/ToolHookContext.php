<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/instructor-agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Hooks\Data;

use Cognesy\Addons\Agent\Core\Data\AgentExecution;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Polyglot\Inference\Data\ToolCall;

/**
 * Context for tool-related hook events (PreToolUse, PostToolUse).
 *
 * Provides access to:
 * - The tool call being executed
 * - The tool execution result (PostToolUse only)
 * - The current agent state
 *
 * For PreToolUse events, the execution is null (not yet executed).
 * For PostToolUse events, the execution contains the result.
 *
 * @example
 * function beforeTool(ToolHookContext $ctx): HookOutcome {
 *     $toolName = $ctx->toolCall()->name();
 *     $args = $ctx->toolCall()->args();
 *
 *     if ($this->isDangerous($toolName, $args)) {
 *         return HookOutcome::block('Dangerous command');
 *     }
 *     return HookOutcome::proceed();
 * }
 *
 * @example
 * function afterTool(ToolHookContext $ctx): HookOutcome {
 *     $execution = $ctx->execution();
 *     if ($execution->result()->isFailure()) {
 *         $this->logger->error("Tool failed: {$ctx->toolCall()->name()}");
 *     }
 *     return HookOutcome::proceed();
 * }
 */
final readonly class ToolHookContext extends AbstractHookContext
{
    /**
     * @param ToolCall $toolCall The tool call being executed
     * @param AgentState $state The current agent state
     * @param HookEvent $event The specific event (PreToolUse or PostToolUse)
     * @param AgentExecution|null $execution The execution result (PostToolUse only)
     * @param array<string, mixed> $metadata Additional context metadata
     */
    public function __construct(
        private ToolCall $toolCall,
        AgentState $state,
        private HookEvent $event = HookEvent::PreToolUse,
        private ?AgentExecution $execution = null,
        array $metadata = [],
    ) {
        parent::__construct($state, $metadata);
    }

    /**
     * Create a context for PreToolUse event.
     *
     * @param ToolCall $toolCall The tool call about to be executed
     * @param AgentState $state The current agent state
     * @param array<string, mixed> $metadata Additional metadata
     */
    public static function beforeTool(
        ToolCall $toolCall,
        AgentState $state,
        array $metadata = [],
    ): self {
        return new self($toolCall, $state, HookEvent::PreToolUse, null, $metadata);
    }

    /**
     * Create a context for PostToolUse event.
     *
     * @param ToolCall $toolCall The tool call that was executed
     * @param AgentExecution $execution The execution result
     * @param AgentState $state The current agent state
     * @param array<string, mixed> $metadata Additional metadata
     */
    public static function afterTool(
        ToolCall $toolCall,
        AgentExecution $execution,
        AgentState $state,
        array $metadata = [],
    ): self {
        return new self($toolCall, $state, HookEvent::PostToolUse, $execution, $metadata);
    }

    #[\Override]
    public function eventType(): HookEvent
    {
        return $this->event;
    }

    /**
     * Get the tool call.
     */
    public function toolCall(): ToolCall
    {
        return $this->toolCall;
    }

    /**
     * Get the tool execution result.
     *
     * Only available for PostToolUse events.
     */
    public function execution(): ?AgentExecution
    {
        return $this->execution;
    }

    /**
     * Check if this is a PreToolUse event.
     */
    public function isBeforeTool(): bool
    {
        return $this->event === HookEvent::PreToolUse;
    }

    /**
     * Check if this is a PostToolUse event.
     */
    public function isAfterTool(): bool
    {
        return $this->event === HookEvent::PostToolUse;
    }

    #[\Override]
    public function withState(AgentState $state): static
    {
        return new self($this->toolCall, $state, $this->event, $this->execution, $this->metadata);
    }

    #[\Override]
    public function withMetadata(string $key, mixed $value): static
    {
        return new self(
            $this->toolCall,
            $this->state,
            $this->event,
            $this->execution,
            [...$this->metadata, $key => $value],
        );
    }

    /**
     * Create a new context with a modified tool call.
     *
     * Only valid for PreToolUse events (modifying args before execution).
     */
    public function withToolCall(ToolCall $toolCall): self
    {
        return new self($toolCall, $this->state, $this->event, $this->execution, $this->metadata);
    }

    /**
     * Create a new context with a modified execution result.
     *
     * Only valid for PostToolUse events (modifying result after execution).
     */
    public function withExecution(AgentExecution $execution): self
    {
        return new self($this->toolCall, $this->state, $this->event, $execution, $this->metadata);
    }
}
