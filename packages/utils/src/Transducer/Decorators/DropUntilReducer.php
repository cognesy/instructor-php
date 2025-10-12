<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Decorators;

use Closure;
use Cognesy\Utils\Transducer\Contracts\Reducer;

final class DropUntilReducer implements Reducer
{
    private bool $dropping = true;

    /**
     * @param Closure(mixed): bool $conditionFn
     */
    public function __construct(
        private Closure $conditionFn,
        private Reducer $reducer,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if ($this->dropping) {
            if (($this->conditionFn)($reducible)) {
                $this->dropping = false;
                return $this->reducer->step($accumulator, $reducible);
            }
            return $accumulator;
        }

        return $this->reducer->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->reducer->complete($accumulator);
    }
}
