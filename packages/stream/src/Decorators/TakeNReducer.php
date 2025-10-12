<?php declare(strict_types=1);

namespace Cognesy\Stream\Decorators;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Support\Reduced;

final class TakeNReducer implements Reducer
{
    private int $taken = 0;

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
        if ($this->taken >= $this->amount) {
            return new Reduced($accumulator);
        }
        $this->taken++;
        return $this->reducer->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->reducer->complete($accumulator);
    }
}
