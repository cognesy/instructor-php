<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Hooks\Matchers;

use Closure;
use Cognesy\Agents\Agent\Hooks\Contracts\HookContext;
use Cognesy\Agents\Agent\Hooks\Contracts\HookMatcher;

/**
 * Matcher that uses a custom callable for matching logic.
 *
 * Provides maximum flexibility for complex matching conditions
 * that can't be expressed with other matchers.
 *
 * @example
 * // Match when agent has been running for more than 30 seconds
 * $matcher = new CallableMatcher(function (HookContext $ctx): bool {
 *     $started = $ctx->state()->createdAt();
 *     return (new DateTime())->getTimestamp() - $started->getTimestamp() > 30;
 * });
 *
 * @example
 * // Match based on state metadata
 * $matcher = new CallableMatcher(
 *     fn(HookContext $ctx) => $ctx->state()->metadata()->get('priority') === 'high'
 * );
 */
final readonly class CallableMatcher implements HookMatcher
{
    /** @var Closure(HookContext): bool */
    private Closure $predicate;

    /**
     * @param callable(HookContext): bool $predicate The matching function
     */
    public function __construct(callable $predicate)
    {
        $this->predicate = Closure::fromCallable($predicate);
    }

    #[\Override]
    public function matches(HookContext $context): bool
    {
        return ($this->predicate)($context);
    }
}
