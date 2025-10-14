<?php declare(strict_types=1);

namespace Cognesy\Stream\Sinks\Select;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Support\Reduced;

/**
 * A reducer that finds the first item matching a predicate.
 *
 * Example:
 * [1, 2, 3, 4] |> find(fn($x) => $x > 2) // returns 3
 */
final readonly class FindReducer implements Reducer
{
    /** @var Closure(mixed): bool */
    private Closure $predicateFn;
    private mixed $default;

    /**
     * @param Closure(mixed): bool $predicateFn
     */
    public function __construct(Closure $predicateFn, mixed $default = null) {
        $this->predicateFn = $predicateFn;
        $this->default = $default;
    }

    #[\Override]
    public function init(): mixed {
        return $this->default;
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if (($this->predicateFn)($reducible)) {
            return new Reduced($reducible);
        }

        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
