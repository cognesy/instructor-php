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
 * Hook for intercepting tool calls before execution.
 *
 * The callback must return a HookOutcome:
 * - HookOutcome::proceed() to allow the tool call
 * - HookOutcome::proceed($ctx->withToolCall($modified)) to modify the tool call
 * - HookOutcome::block($reason) to prevent the tool call
 * - HookOutcome::stop($reason) to halt agent execution
 *
 * @example
 * // Block dangerous commands
 * $hook = new BeforeToolHook(
 *     callback: function (ToolHookContext $ctx): HookOutcome {
 *         $command = $ctx->toolCall()->args()['command'] ?? '';
 *         if (str_contains($command, 'rm -rf')) {
 *             return HookOutcome::block('Dangerous command blocked');
 *         }
 *         return HookOutcome::proceed();
 *     },
 *     matcher: new ToolNameMatcher('bash'),
 * );
 *
 * @example
 * // Modify tool arguments
 * $hook = new BeforeToolHook(
 *     callback: function (ToolHookContext $ctx): HookOutcome {
 *         $toolCall = $ctx->toolCall();
 *         $args = $toolCall->args();
 *         $args['timeout'] = 30;
 *         return HookOutcome::proceed($ctx->withToolCall($toolCall->withArgs($args)));
 *     },
 * );
 */
final readonly class BeforeToolHook implements Hook
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
        // Only process PreToolUse events
        if (!$context instanceof ToolHookContext || $context->eventType() !== HookType::PreToolUse) {
            return $next($context);
        }

        // Skip if matcher doesn't match
        if ($this->matcher !== null && !$this->matcher->matches($context)) {
            return $next($context);
        }

        // Execute callback - must return HookOutcome
        $outcome = ($this->callback)($context);

        // If blocked or stopped, return immediately
        if ($outcome->isBlocked() || $outcome->isStopped()) {
            return $outcome;
        }

        // Pass along (with potentially modified context)
        $effectiveContext = $outcome->context() ?? $context;
        return $next($effectiveContext);
    }
}
