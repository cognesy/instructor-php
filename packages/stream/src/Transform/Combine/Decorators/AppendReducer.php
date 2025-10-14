<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Combine\Decorators;

use Cognesy\Stream\Contracts\Reducer;

/**
 * A reducer that appends a set of values to the end
 * of the reduction process.
 *
 * Example:
 * [1, 2, 3] -> append([4, 5]) -> [1, 2, 3, 4, 5]
 */
final class AppendReducer implements Reducer
{
    public function __construct(
        private Reducer $inner,
        private array $values,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        return $this->inner->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        foreach ($this->values as $value) {
            $accumulator = $this->inner->step($accumulator, $value);
        }
        return $this->inner->complete($accumulator);
    }
}
