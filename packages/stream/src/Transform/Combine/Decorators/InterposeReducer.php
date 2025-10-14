<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Combine\Decorators;

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
        private Reducer $inner,
        private mixed $separator,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if ($this->isFirst) {
            $this->isFirst = false;
            return $this->inner->step($accumulator, $reducible);
        }
        $accumulator = $this->inner->step($accumulator, $this->separator);
        return $this->inner->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}