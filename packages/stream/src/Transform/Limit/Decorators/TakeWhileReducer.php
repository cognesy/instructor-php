<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Limit\Decorators;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Support\Reduced;

final readonly class TakeWhileReducer implements Reducer
{
    public function __construct(
        private Reducer $inner,
        /** @var Closure(mixed): bool $conditionFn */
        private Closure $conditionFn,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if (($this->conditionFn)($reducible)) {
            return $this->inner->step($accumulator, $reducible);
        }
        return new Reduced($accumulator);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}