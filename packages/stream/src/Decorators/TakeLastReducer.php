<?php declare(strict_types=1);

namespace Cognesy\Stream\Decorators;

use Cognesy\Stream\Contracts\Reducer;

final class TakeLastReducer implements Reducer
{
    private array $buffer = [];

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
        $this->buffer[] = $reducible;

        if (count($this->buffer) > $this->amount) {
            array_shift($this->buffer);
        }

        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        foreach ($this->buffer as $value) {
            $accumulator = $this->reducer->step($accumulator, $value);
        }
        return $this->reducer->complete($accumulator);
    }
}
