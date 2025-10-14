<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Filter\Decorators;

use Closure;
use Cognesy\Stream\Contracts\Reducer;

final readonly class FilterReducer implements Reducer
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
        return match(true) {
            (($this->conditionFn)($reducible)) => $this->inner->step($accumulator, $reducible),
            default => $accumulator,
        };
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}