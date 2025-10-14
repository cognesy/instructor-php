<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Map\Decorators;

use Closure;
use Cognesy\Stream\Contracts\Reducer;

/**
 * A reducer that maps each input value to a new value using a provided mapping function,
 * which receives both the value and its index, before passing it to the underlying reducer.
 *
 * This is useful for scenarios where the index of the element is needed in the mapping logic.
 *
 * Example:
 * [0 => 'a', 1 => 'b', 2 => 'c']
 * with mapFn() = fn($v, $i) => "$i:$v" becomes
 * ['0:a', '1:b', '2:c']
 */
final class MapIndexedReducer implements Reducer
{
    private int $index = 0;

    /**
     * @param Closure(mixed, int): mixed $mapFn
     */
    public function __construct(
        private Reducer $inner,
        private Closure $mapFn,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $mapped = ($this->mapFn)($reducible, $this->index);
        $this->index++;
        return $this->inner->step($accumulator, $mapped);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
