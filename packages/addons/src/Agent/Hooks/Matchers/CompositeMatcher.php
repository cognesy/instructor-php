<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Hooks\Matchers;

use Cognesy\Addons\Agent\Hooks\Contracts\HookContext;
use Cognesy\Addons\Agent\Hooks\Contracts\HookMatcher;

/**
 * Matcher that combines multiple matchers with AND or OR logic.
 *
 * Allows building complex matching conditions from simple matchers.
 *
 * @example
 * // Match bash tool during first 5 steps
 * $matcher = CompositeMatcher::and(
 *     new ToolNameMatcher('bash'),
 *     new CallableMatcher(fn($ctx) => $ctx->state()->stepCount() < 5),
 * );
 *
 * @example
 * // Match either read_* or write_* tools
 * $matcher = CompositeMatcher::or(
 *     new ToolNameMatcher('read_*'),
 *     new ToolNameMatcher('write_*'),
 * );
 */
final readonly class CompositeMatcher implements HookMatcher
{
    private const MODE_AND = 'and';
    private const MODE_OR = 'or';

    /**
     * @param array<HookMatcher> $matchers
     * @param string $mode
     */
    private function __construct(
        private array $matchers,
        private string $mode,
    ) {}

    /**
     * Create a matcher that requires ALL matchers to match (AND logic).
     *
     * @param HookMatcher ...$matchers The matchers that must all match
     */
    public static function and(HookMatcher ...$matchers): self
    {
        return new self($matchers, self::MODE_AND);
    }

    /**
     * Create a matcher that requires ANY matcher to match (OR logic).
     *
     * @param HookMatcher ...$matchers The matchers where at least one must match
     */
    public static function or(HookMatcher ...$matchers): self
    {
        return new self($matchers, self::MODE_OR);
    }

    #[\Override]
    public function matches(HookContext $context): bool
    {
        if ($this->matchers === []) {
            return true;
        }

        return match ($this->mode) {
            self::MODE_AND => $this->matchesAll($context),
            self::MODE_OR => $this->matchesAny($context),
            default => false,
        };
    }

    /**
     * Check if ALL matchers match (AND logic).
     */
    private function matchesAll(HookContext $context): bool
    {
        foreach ($this->matchers as $matcher) {
            if (!$matcher->matches($context)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if ANY matcher matches (OR logic).
     */
    private function matchesAny(HookContext $context): bool
    {
        foreach ($this->matchers as $matcher) {
            if ($matcher->matches($context)) {
                return true;
            }
        }
        return false;
    }
}
