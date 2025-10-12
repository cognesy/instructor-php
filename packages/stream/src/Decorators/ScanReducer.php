<?php declare(strict_types=1);

namespace Cognesy\Stream\Decorators;

use Closure;
use Cognesy\Stream\Contracts\Reducer;

final class ScanReducer implements Reducer
{
    /**
     * @param Closure(mixed, mixed): mixed $scanFn
     */
    public function __construct(
        private Reducer $reducer,
        private Closure $scanFn,
        private mixed $state,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $this->state = ($this->scanFn)($this->state, $reducible);
        return $this->reducer->step($accumulator, $this->state);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->reducer->complete($accumulator);
    }
}
