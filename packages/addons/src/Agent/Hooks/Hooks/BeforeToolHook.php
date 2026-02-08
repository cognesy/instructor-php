<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/instructor-agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Hooks\Hooks;

use Closure;
use Cognesy\Addons\Agent\Hooks\Contracts\Hook;
use Cognesy\Addons\Agent\Hooks\Contracts\HookContext;
use Cognesy\Addons\Agent\Hooks\Contracts\HookMatcher;
use Cognesy\Addons\Agent\Hooks\Data\HookEvent;
use Cognesy\Addons\Agent\Hooks\Data\HookOutcome;
use Cognesy\Addons\Agent\Hooks\Data\ToolHookContext;
use Cognesy\Polyglot\Inference\Data\ToolCall;

/**
 * Hook for intercepting tool calls before execution.
 *
 * The callback can:
 * - Return HookOutcome::proceed() to allow the tool call
 * - Return HookOutcome::proceed($modifiedContext) to modify the tool call
 * - Return HookOutcome::block($reason) to prevent the tool call
 * - Return HookOutcome::stop($reason) to halt agent execution
 *
 * Simplified callback signatures are also supported:
 * - Return null to block the tool call
 * - Return ToolCall to proceed with modified args
 * - Return void/anything else to proceed unchanged
 *
 * @example
 * // Block dangerous commands using HookOutcome
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
    private Closure $callback;

    /**
     * @param callable(ToolHookContext): (HookOutcome|ToolCall|null|void) $callback
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
        if (!$context instanceof ToolHookContext || $context->eventType() !== HookEvent::PreToolUse) {
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
            // If blocked or stopped, return immediately
            if ($result->isBlocked() || $result->isStopped()) {
                return $result;
            }
            // If proceed with modified context, pass it along
            $effectiveContext = $result->context() ?? $context;
            return $next($effectiveContext);
        }

        // Handle null = block
        if ($result === null) {
            return HookOutcome::block('Blocked by hook');
        }

        // Handle ToolCall = proceed with modified call
        if ($result instanceof ToolCall) {
            return $next($context->withToolCall($result));
        }

        // Anything else = proceed unchanged
        return $next($context);
    }
}
