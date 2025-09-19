<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Continuation\Criteria;

use Closure;
use Cognesy\Addons\Core\Continuation\CanDecideToContinue;

/**
 * Stops when the current step does not contain any tool calls.
 *
 * @template TState of object
 */
final readonly class ToolCallPresenceCheck implements CanDecideToContinue
{
    /** @var Closure(TState): bool */
    private Closure $hasToolCallsResolver;

    /**
     * @param callable(TState): bool $hasToolCallsResolver
     */
    public function __construct(callable $hasToolCallsResolver) {
        $this->hasToolCallsResolver = Closure::fromCallable($hasToolCallsResolver);
    }

    public function canContinue(object $state): bool {
        return ($this->hasToolCallsResolver)($state);
    }
}
