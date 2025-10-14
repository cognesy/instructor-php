<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Limit\Decorators;

use Cognesy\Stream\Contracts\Reducer;

final class TakeLastReducer implements Reducer
{
    private array $buffer = [];

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
        $this->buffer[] = $reducible;

        if (count($this->buffer) > $this->amount) {
            array_shift($this->buffer);
        }

        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        foreach ($this->buffer as $value) {
            $accumulator = $this->inner->step($accumulator, $value);
        }
        return $this->inner->complete($accumulator);
    }
}
