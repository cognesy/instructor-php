<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Decorators;

use Cognesy\Utils\Transducer\Contracts\Reducer;

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
        private array $values,
        private Reducer $reducer,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        return $this->reducer->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        foreach ($this->values as $value) {
            $accumulator = $this->reducer->step($accumulator, $value);
        }
        return $this->reducer->complete($accumulator);
    }
}
