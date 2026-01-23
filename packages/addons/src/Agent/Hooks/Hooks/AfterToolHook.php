<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Hooks\Hooks;

use Closure;
use Cognesy\Addons\Agent\Core\Data\AgentExecution;
use Cognesy\Addons\Agent\Hooks\Contracts\Hook;
use Cognesy\Addons\Agent\Hooks\Contracts\HookContext;
use Cognesy\Addons\Agent\Hooks\Contracts\HookMatcher;
use Cognesy\Addons\Agent\Hooks\Data\HookEvent;
use Cognesy\Addons\Agent\Hooks\Data\HookOutcome;
use Cognesy\Addons\Agent\Hooks\Data\ToolHookContext;

/**
 * Hook for intercepting tool calls after execution.
 *
 * The callback receives the execution result and can:
 * - Return HookOutcome::proceed() to continue unchanged
 * - Return HookOutcome::proceed($modifiedContext) to modify the execution result
 * - Return HookOutcome::stop($reason) to halt agent execution
 *
 * Simplified callback signatures are also supported:
 * - Return AgentExecution to replace the result
 * - Return void/anything else to keep the original result
 *
 * @example
 * // Log all tool executions
 * $hook = new AfterToolHook(
 *     callback: function (ToolHookContext $ctx): HookOutcome {
 *         $execution = $ctx->execution();
 *         $this->logger->info("Tool {$ctx->toolCall()->name()} completed", [
 *             'success' => $execution->result()->isSuccess(),
 *             'duration' => $execution->endedAt()->getTimestamp() - $execution->startedAt()->getTimestamp(),
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
    private Closure $callback;

    /**
     * @param callable(ToolHookContext): (HookOutcome|AgentExecution|void) $callback
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
        if (!$context instanceof ToolHookContext || $context->eventType() !== HookEvent::PostToolUse) {
            return $next($context);
        }

        // Skip if matcher doesn't match
        if ($this->matcher !== null && !$this->matcher->matches($context)) {
            return $next($context);
        }

        // Execute callback
        $result = ($this->callback)($context);

        // Handle HookOutcome directly
        if ($result instanceof HookOutcome) {
            // If stopped, return immediately
            if ($result->isStopped()) {
                return $result;
            }
            // If proceed with modified context, pass it along
            $effectiveContext = $result->context() ?? $context;
            return $next($effectiveContext);
        }

        // Handle AgentExecution = proceed with modified execution
        if ($result instanceof AgentExecution) {
            return $next($context->withExecution($result));
        }

        // Anything else = proceed unchanged
        return $next($context);
    }
}
