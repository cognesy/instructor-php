<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Hooks\Contracts;

use Cognesy\Addons\Agent\Hooks\Data\HookOutcome;

/**
 * Core interface for all hooks in the unified hook system.
 *
 * Hooks are middleware-style processors that intercept specific lifecycle
 * events in the agent execution. They can:
 * - Allow execution to proceed (with or without modifications)
 * - Block a specific action (e.g., block a dangerous tool call)
 * - Stop the entire agent execution
 *
 * The hook pattern follows chain-of-responsibility, where each hook receives
 * a context and a "next" callable. Hooks can:
 * - Call $next() to pass control down the chain
 * - Return early to short-circuit the chain
 * - Modify the context before passing it along
 *
 * @example
 * // Logging hook
 * class LoggingHook implements Hook {
 *     public function handle(HookContext $context, callable $next): HookOutcome {
 *         $this->logger->info("Before: {$context->eventType()->value}");
 *         $outcome = $next($context);
 *         $this->logger->info("After: {$context->eventType()->value}");
 *         return $outcome;
 *     }
 * }
 *
 * @example
 * // Blocking hook
 * class SecurityHook implements Hook {
 *     public function handle(HookContext $context, callable $next): HookOutcome {
 *         if ($this->isDangerous($context)) {
 *             return HookOutcome::block('Security violation');
 *         }
 *         return $next($context);
 *     }
 * }
 */
interface Hook
{
    /**
     * Process the hook and return an outcome.
     *
     * @param HookContext $context Event-specific context with state and event data
     * @param callable(HookContext): HookOutcome $next Next handler in chain (or terminal)
     * @return HookOutcome Result of hook processing (proceed/block/stop)
     */
    public function handle(HookContext $context, callable $next): HookOutcome;
}
