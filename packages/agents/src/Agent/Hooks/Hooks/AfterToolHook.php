<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Hooks\Hooks;

use Closure;
use Cognesy\Agents\Agent\Hooks\Contracts\Hook;
use Cognesy\Agents\Agent\Hooks\Contracts\HookContext;
use Cognesy\Agents\Agent\Hooks\Contracts\HookMatcher;
use Cognesy\Agents\Agent\Hooks\Data\HookOutcome;
use Cognesy\Agents\Agent\Hooks\Data\ToolHookContext;
use Cognesy\Agents\Agent\Hooks\Enums\HookType;

/**
 * Hook for intercepting tool calls after execution.
 *
 * The callback must return a HookOutcome:
 * - HookOutcome::proceed() to continue unchanged
 * - HookOutcome::proceed($ctx->withExecution($modified)) to modify the execution result
 * - HookOutcome::stop($reason) to halt agent execution
 *
 * @example
 * // Log all tool executions
 * $hook = new AfterToolHook(
 *     callback: function (ToolHookContext $ctx): HookOutcome {
 *         $execution = $ctx->execution();
 *         $this->logger->info("Tool {$ctx->toolCall()->name()} completed", [
 *             'success' => $execution->result()->isSuccess(),
 *             'duration' => $execution->completedAt()->getTimestamp() - $execution->startedAt()->getTimestamp(),
 *         ]);
 *         return HookOutcome::proceed();
 *     },
 * );
 *
 * @example
 * // Modify the result
 * $hook = new AfterToolHook(
 *     callback: function (ToolHookContext $ctx): HookOutcome {
 *         $execution = $ctx->execution();
 *         // Create modified execution...
 *         return HookOutcome::proceed($ctx->withExecution($modifiedExecution));
 *     },
 * );
 */
final readonly class AfterToolHook implements Hook
{
    /** @var Closure(ToolHookContext): HookOutcome */
    private Closure $callback;

    /**
     * @param callable(ToolHookContext): HookOutcome $callback
     * @param HookMatcher|null $matcher Optional matcher for conditional execution
     */
    public function __construct(
        callable $callback,
        private ?HookMatcher $matcher = null,
    ) {
        $this->callback = Closure::fromCallable($callback);
    }

    #[\Override]
    public function handle(HookContext $context, callable $next): HookOutcome
    {
        // Only process PostToolUse events
        if (!$context instanceof ToolHookContext || $context->eventType() !== HookType::PostToolUse) {
            return $next($context);
        }

        // Skip if matcher doesn't match
        if ($this->matcher !== null && !$this->matcher->matches($context)) {
            return $next($context);
        }

        // Execute callback - must return HookOutcome
        $outcome = ($this->callback)($context);

        // If stopped, return immediately
        if ($outcome->isStopped()) {
            return $outcome;
        }

        // Pass along (with potentially modified context)
        $effectiveContext = $outcome->context() ?? $context;
        return $next($effectiveContext);
    }
}
