<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Limit\Decorators;

use Cognesy\Stream\Contracts\Reducer;

final class DropFirstReducer implements Reducer
{
    private int $dropped = 0;

    public function __construct(
        private Reducer $inner,
        private int $amount,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if ($this->dropped < $this->amount) {
            $this->dropped++;
            return $accumulator;
        }
        return $this->inner->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
