<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Closure;
use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Contracts\HookContext;
use Cognesy\Agents\AgentHooks\Contracts\HookMatcher;
use Cognesy\Agents\AgentHooks\Data\HookOutcome;

/**
 * Hook implementation that wraps a callable.
 *
 * Provides a convenient way to create hooks from closures or callables
 * without implementing the full Hook interface.
 *
 * The callable receives the context and $next, and must return a HookOutcome.
 *
 * @example
 * // Simple logging hook
 * $hook = new CallableHook(function (HookContext $ctx, callable $next): HookOutcome {
 *     $this->logger->info("Processing: {$ctx->eventType()->value}");
 *     return $next($ctx);
 * });
 *
 * @example
 * // Hook with matcher
 * $hook = new CallableHook(
 *     callback: fn(HookContext $ctx, callable $next): HookOutcome => $next($ctx),
 *     matcher: new ToolNameMatcher('bash'),
 * );
 */
final readonly class CallableHook implements Hook
{
    /** @var Closure(HookContext, callable(HookContext): HookOutcome): HookOutcome */
    private Closure $callback;

    /**
     * @param callable(HookContext, callable(HookContext): HookOutcome): HookOutcome $callback The hook callback
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
        // Skip if matcher doesn't match
        if ($this->matcher !== null && !$this->matcher->matches($context)) {
            return $next($context);
        }

        // Execute callback - must return HookOutcome
        return ($this->callback)($context, $next);
    }
}
