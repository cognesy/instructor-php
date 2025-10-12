<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Decorators;

use Cognesy\Utils\Transducer\Contracts\Reducer;

/**
 * A reducer that drops the last N elements from the input
 * before passing them to the underlying reducer.
 */
final class DropLastReducer implements Reducer
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
            $value = array_shift($this->buffer);
            return $this->reducer->step($accumulator, $value);
        }

        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->reducer->complete($accumulator);
    }
}
