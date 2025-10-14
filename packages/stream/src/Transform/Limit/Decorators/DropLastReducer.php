<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Limit\Decorators;

use Cognesy\Stream\Contracts\Reducer;

/**
 * A reducer that drops the last N elements from the input
 * before passing them to the underlying reducer.
 */
final class DropLastReducer implements Reducer
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
            $value = array_shift($this->buffer);
            return $this->inner->step($accumulator, $value);
        }

        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
