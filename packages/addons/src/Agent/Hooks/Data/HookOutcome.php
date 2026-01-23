<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Hooks\Data;

use Cognesy\Addons\Agent\Hooks\Contracts\HookContext;

/**
 * Represents the outcome of a hook execution.
 *
 * Hook outcomes indicate what should happen after a hook processes:
 *
 * - proceed(): Continue with the operation, optionally with a modified context
 * - block($reason): Block this specific action (e.g., prevent a tool call)
 * - stop($reason): Stop the entire agent execution
 *
 * Semantic differences:
 * - "block" prevents a single action but execution continues
 * - "stop" halts the entire agent execution immediately
 *
 * @example
 * // Allow tool call to proceed
 * return HookOutcome::proceed();
 *
 * @example
 * // Proceed with modified context
 * $newContext = $context->withState($modifiedState);
 * return HookOutcome::proceed($newContext);
 *
 * @example
 * // Block a dangerous command but continue agent
 * return HookOutcome::block('Command contains dangerous pattern');
 *
 * @example
 * // Stop agent execution entirely
 * return HookOutcome::stop('Maximum budget exceeded');
 */
final readonly class HookOutcome
{
    private const TYPE_PROCEED = 'proceed';
    private const TYPE_BLOCK = 'block';
    private const TYPE_STOP = 'stop';

    private function __construct(
        private string $type,
        private ?HookContext $context = null,
        private ?string $reason = null,
    ) {}

    /**
     * Create an outcome that allows execution to proceed.
     *
     * @param HookContext|null $modifiedContext Optional modified context to pass along
     * @return self
     */
    public static function proceed(?HookContext $modifiedContext = null): self
    {
        return new self(self::TYPE_PROCEED, $modifiedContext);
    }

    /**
     * Create an outcome that blocks this specific action.
     *
     * The blocked action will not execute, but agent execution continues.
     * Use this for preventing individual tool calls or actions.
     *
     * @param string $reason Human-readable reason for blocking
     * @return self
     */
    public static function block(string $reason): self
    {
        return new self(self::TYPE_BLOCK, null, $reason);
    }

    /**
     * Create an outcome that stops agent execution entirely.
     *
     * Use this for conditions that require halting all processing,
     * such as budget limits or critical errors.
     *
     * @param string $reason Human-readable reason for stopping
     * @return self
     */
    public static function stop(string $reason): self
    {
        return new self(self::TYPE_STOP, null, $reason);
    }

    /**
     * Check if this outcome allows proceeding.
     */
    public function isProceed(): bool
    {
        return $this->type === self::TYPE_PROCEED;
    }

    /**
     * Check if this outcome blocks the action.
     */
    public function isBlocked(): bool
    {
        return $this->type === self::TYPE_BLOCK;
    }

    /**
     * Check if this outcome stops execution.
     */
    public function isStopped(): bool
    {
        return $this->type === self::TYPE_STOP;
    }

    /**
     * Get the modified context, if any.
     *
     * @return HookContext|null The modified context, or null if unchanged
     */
    public function context(): ?HookContext
    {
        return $this->context;
    }

    /**
     * Get the reason for blocking or stopping.
     *
     * @return string|null The reason, or null if proceeding
     */
    public function reason(): ?string
    {
        return $this->reason;
    }

    /**
     * Get the outcome type as a string.
     *
     * @return string One of 'proceed', 'block', 'stop'
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Convert to array for serialization/debugging.
     *
     * @return array{type: string, reason: string|null, hasContext: bool}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'reason' => $this->reason,
            'hasContext' => $this->context !== null,
        ];
    }
}
