<?php declare(strict_types=1);

namespace Cognesy\Stream\Decorators;

use Cognesy\Stream\Contracts\Reducer;

/**
 * A reducer that interposes a separator between elements.
 *
 * Example:
 * [1, 2, 3] with separator 0 becomes [1, 0, 2, 0, 3]
 */
final class InterposeReducer implements Reducer
{
    private bool $isFirst = true;

    public function __construct(
        private mixed $separator,
        private Reducer $reducer,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if ($this->isFirst) {
            $this->isFirst = false;
            return $this->reducer->step($accumulator, $reducible);
        }
        $accumulator = $this->reducer->step($accumulator, $this->separator);
        return $this->reducer->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->reducer->complete($accumulator);
    }
}