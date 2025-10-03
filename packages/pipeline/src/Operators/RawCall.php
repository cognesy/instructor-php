<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\StateContracts\CanCarryState;

/**
 * Fast execution operator that directly executes closures without any overhead.
 * Expects closure to handle (CanCarryState, ?callable) -> CanCarryState pattern.
 */
readonly final class RawCall implements CanProcessState
{
    /** @var \Closure(CanCarryState, ?callable):CanCarryState */
    private \Closure $closure;

    /**
     * @param \Closure(CanCarryState, ?callable):CanCarryState $closure
     */
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

    #[\Override]
    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        return ($this->closure)($state, $next);
    }
}