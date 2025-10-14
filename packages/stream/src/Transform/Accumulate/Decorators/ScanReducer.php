<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Accumulate\Decorators;

use Closure;
use Cognesy\Stream\Contracts\Reducer;

/**
 * A reducer that applies a scan (also known as accumulate or prefix sum)
 * operation before passing the result to the underlying reducer.
 *
 * The scan operation maintains an internal state that is updated
 * with each input element using the provided scan function.
 *
 * This is useful for scenarios where you want to keep a running total
 * or any other cumulative computation while reducing a stream of data.
 *
 * Example:
 * - Input: [1, 2, 3, 4]
 * - Scan function: (acc, x) => acc + x
 * - Initial state: 0
 * - Output: [1, 3, 6, 10] (which are the cumulative sums)
 * - If the underlying reducer is a sum reducer, the final result would be 20.
 */
final class ScanReducer implements Reducer
{
    /**
     * @param Closure(mixed, mixed): mixed $scanFn
     */
    public function __construct(
        private Reducer $inner,
        private Closure $scanFn,
        private mixed $state,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $this->state = ($this->scanFn)($this->state, $reducible);
        return $this->inner->step($accumulator, $this->state);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
