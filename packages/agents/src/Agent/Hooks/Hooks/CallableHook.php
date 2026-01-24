<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Hooks\Hooks;

use Closure;
use Cognesy\Agents\Agent\Hooks\Contracts\Hook;
use Cognesy\Agents\Agent\Hooks\Contracts\HookContext;
use Cognesy\Agents\Agent\Hooks\Contracts\HookMatcher;
use Cognesy\Agents\Agent\Hooks\Data\HookOutcome;

/**
 * Hook implementation that wraps a callable.
 *
 * Provides a convenient way to create hooks from closures or callables
 * without implementing the full Hook interface.
 *
 * The callable receives the context and returns a HookOutcome.
 * If the callable returns something other than HookOutcome, it's treated
 * as a proceed signal.
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
 *     callback: fn(HookContext $ctx, callable $next) => $next($ctx),
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

        $result = ($this->callback)($context, $next);

        // If callback returns HookOutcome, use it
        if ($result instanceof HookOutcome) {
            return $result;
        }

        // Otherwise, treat as proceed
        return HookOutcome::proceed();
    }
}
