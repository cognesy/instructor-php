<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Flatten\Decorators;

use Cognesy\Stream\Contracts\Reducer;

/**
 * A reducer that flattens nested iterables up to a specified depth before passing
 * the values to the underlying reducer.
 *
 * For depth = 0, no flattening is performed.
 * For depth = 1, only the first level of nested iterables is flattened.
 * For depth = n, flattening is performed up to n levels of nested iterables.
 * To fully flatten all levels, use PHP_INT_MAX as depth.
 *
 * Example:
 * [1, [2, 3], [[4, 5]], [[[6]]]]
 * - depth = 0 => [1, [2, 3], [[4, 5]], [[[6]]]]
 * - depth = 1 => [1, 2, 3, [4, 5], [[6]]]
 * - depth = 2 => [1, 2, 3, 4, 5, [6]]
 * - depth = 3 => [1, 2, 3, 4, 5, 6]
 */
final class FlattenReducer implements Reducer
{
    public function __construct(
        private Reducer $inner,
        private int $depth,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        return $this->flattenValue($accumulator, $reducible, $this->depth);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }

    private function flattenValue(mixed $accumulator, mixed $value, int $depth): mixed {
        if ($depth <= 0 || !is_iterable($value)) {
            return $this->inner->step($accumulator, $value);
        }
        foreach ($value as $item) {
            $accumulator = $this->flattenValue($accumulator, $item, $depth - 1);
        }
        return $accumulator;
    }
}
