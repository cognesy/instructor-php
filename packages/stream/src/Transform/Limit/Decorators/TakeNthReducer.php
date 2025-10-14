<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Limit\Decorators;

use Cognesy\Stream\Contracts\Reducer;

final class TakeNthReducer implements Reducer
{
    private int $index = 0;

    public function __construct(
        private Reducer $inner,
        private int $nth,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $shouldTake = ($this->index % $this->nth) === 0;
        $this->index++;

        if ($shouldTake) {
            return $this->inner->step($accumulator, $reducible);
        }
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
