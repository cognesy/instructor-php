<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;

/**
 * Fast execution operator that directly executes closures without any overhead.
 * Expects closure to handle (ProcessingState, ?callable) -> ProcessingState pattern.
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
     * @param callable(ProcessingState, ?callable):ProcessingState $closure
     */
    public static function with(callable $closure): self {
        return new self($closure(...));
    }

    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        return ($this->closure)($state, $next);
    }
}