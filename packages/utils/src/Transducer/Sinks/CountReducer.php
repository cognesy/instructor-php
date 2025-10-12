<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Sinks;

use Closure;
use Cognesy\Utils\Transducer\Contracts\Reducer;

/**
 * Counts the number of items in a collection, optionally
 * filtered by a predicate function.
 *
 * If no predicate function is provided, it counts all items.
 * If a predicate function is provided, it counts only the items for which the predicate returns true.
 */
final readonly class CountReducer implements Reducer
{
    /** @var Closure(mixed): bool|null */
    private ?Closure $predicateFn;

    /**
     * @param Closure(mixed): bool|null $predicateFn
     */
    public function __construct(?Closure $predicateFn = null) {
        $this->predicateFn = $predicateFn;
    }

    #[\Override]
    public function init(): mixed {
        return 0;
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if ($this->predicateFn === null) {
            return $accumulator + 1;
        }

        return ($this->predicateFn)($reducible)
            ? $accumulator + 1
            : $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
