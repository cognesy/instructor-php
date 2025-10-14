<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Limit\Decorators;

use Closure;
use Cognesy\Stream\Contracts\Reducer;

final class DropWhileReducer implements Reducer
{
    private bool $dropping = true;

    /**
     * @param Closure(mixed): bool $conditionFn
     */
    public function __construct(
        private Reducer $inner,
        private Closure $conditionFn,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if ($this->dropping && ($this->conditionFn)($reducible)) {
            return $accumulator;
        }
        $this->dropping = false;
        return $this->inner->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
