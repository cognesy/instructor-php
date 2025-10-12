<?php declare(strict_types=1);

namespace Cognesy\Stream\Decorators;

use Cognesy\Stream\Contracts\Reducer;

final class DropFirstReducer implements Reducer
{
    private int $dropped = 0;

    public function __construct(
        private int $amount,
        private Reducer $reducer,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if ($this->dropped < $this->amount) {
            $this->dropped++;
            return $accumulator;
        }
        return $this->reducer->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->reducer->complete($accumulator);
    }
}
