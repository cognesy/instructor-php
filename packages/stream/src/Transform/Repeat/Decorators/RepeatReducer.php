<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Repeat\Decorators;

use Cognesy\Stream\Contracts\Reducer;

final readonly class RepeatReducer implements Reducer
{
    public function __construct(
        private Reducer $inner,
        private int $times,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        for ($i = 0; $i < $this->times; $i++) {
            $accumulator = $this->inner->step($accumulator, $reducible);
        }
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
