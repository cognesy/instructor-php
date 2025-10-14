<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Limit\Decorators;

use Cognesy\Stream\Contracts\Reducer;

/**
 * A reducer that randomly samples input based on a given probability.
 *
 * Each input item has a chance, defined by the probability (between 0 and 1),
 * to be processed by the underlying reducer. If the random value is less than
 * the probability, the item is passed to the reducer; otherwise, it is skipped.
 *
 * Example:
 * [1, 2, 3, 4, 5]
 * with probability 0.4 might result in
 * [2, 5]
 * being processed.
 */
final class RandomSampleReducer implements Reducer
{
    public function __construct(
        private Reducer $inner,
        private float $probability,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        // Generate random float between 0 and 1
        $random = mt_rand() / mt_getrandmax();

        if ($random < $this->probability) {
            return $this->inner->step($accumulator, $reducible);
        }

        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
