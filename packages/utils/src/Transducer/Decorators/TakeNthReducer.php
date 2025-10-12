<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Decorators;

use Cognesy\Utils\Transducer\Contracts\Reducer;

final class TakeNthReducer implements Reducer
{
    private int $index = 0;

    public function __construct(
        private int $nth,
        private Reducer $reducer,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $shouldTake = ($this->index % $this->nth) === 0;
        $this->index++;

        if ($shouldTake) {
            return $this->reducer->step($accumulator, $reducible);
        }
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->reducer->complete($accumulator);
    }
}
