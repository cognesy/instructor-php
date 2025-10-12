<?php declare(strict_types=1);

namespace Cognesy\Stream\Decorators;

use Cognesy\Stream\Contracts\Reducer;

final class RandomSampleReducer implements Reducer
{
    public function __construct(
        private float $probability,
        private Reducer $reducer,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        // Generate random float between 0 and 1
        $random = mt_rand() / mt_getrandmax();

        if ($random < $this->probability) {
            return $this->reducer->step($accumulator, $reducible);
        }

        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->reducer->complete($accumulator);
    }
}
