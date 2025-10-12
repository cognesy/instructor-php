<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Decorators;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Reduced;

/**
 * A reducer that concatenates elements from nested
 * iterables into a single sequence.
 *
 * Example:
 * [1, [2, 3], 4, [5, 6]] becomes [1, 2, 3, 4, 5, 6]
 */
final class CatReducer implements Reducer
{
    public function __construct(
        private Reducer $reducer,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if (is_iterable($reducible)) {
            foreach ($reducible as $item) {
                $accumulator = $this->reducer->step($accumulator, $item);
                if ($accumulator instanceof Reduced) {
                    return $accumulator;
                }
            }
            return $accumulator;
        }
        return $this->reducer->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->reducer->complete($accumulator);
    }
}
