<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Misc\Decorators;

use Closure;
use Cognesy\Stream\Contracts\Reducer;

final readonly class TapReducer implements Reducer
{
    public function __construct(
        private Reducer $inner,
        /** @var Closure(mixed): void $sideEffectFn */
        private Closure $sideEffectFn,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        ($this->sideEffectFn)($reducible);
        return $this->inner->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}