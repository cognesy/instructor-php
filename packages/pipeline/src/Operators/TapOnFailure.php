<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\StateContracts\CanCarryState;

/**
 * Middleware that executes a callback when processing results in failure.
 *
 * This provides a convenient way to add "onFailure" hooks while using the
 * middleware pattern. The callback receives the failed state and can
 * perform side effects like logging, alerting, or metrics collection.
 *
 * Note: This middleware does NOT modify the failure - it's purely for
 * side effects. The failure continues to propagate normally.
 */
readonly final class TapOnFailure implements CanProcessState
{
    public function __construct(
        private CanProcessState $operator,
    ) {}

    /**
     * @param callable(CanCarryState):void $callback
     */
    public static function with(callable $callback): self {
        /** @psalm-suppress InvalidArgument - Callback returns void for side effects, wrapped to work with state pipeline */
        return new self(Call::withState($callback));
    }

    /**
     * @param callable(CanCarryState):CanCarryState $next
     */
    #[\Override]
    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        $newState = $next ? $next($state) : $state;
        if (!$newState->isFailure()) {
            return $newState;
        }

        $this->operator->process($newState);
        return $newState;
    }
}