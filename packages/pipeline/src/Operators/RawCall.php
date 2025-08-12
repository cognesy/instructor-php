<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanCarryState;
use Cognesy\Pipeline\Contracts\CanProcessState;

/**
 * Fast execution operator that directly executes closures without any overhead.
 * Expects closure to handle (CanCarryState, ?callable) -> CanCarryState pattern.
 */
readonly final class RawCall implements CanProcessState
{
    private \Closure $closure;

    private function __construct(\Closure $closure) {
        $this->closure = $closure;
    }

    /**
     * Create a FastCall operator from a user closure.
     *
     * @param callable(CanCarryState, ?callable):CanCarryState $closure
     */
    public static function with(callable $closure): self {
        return new self($closure(...));
    }

    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        return ($this->closure)($state, $next);
    }
}