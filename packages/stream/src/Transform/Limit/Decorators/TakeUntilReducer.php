<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Limit\Decorators;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Support\Reduced;

final class TakeUntilReducer implements Reducer {
    public function __construct(
        private readonly Reducer $inner,
        /** @var Closure(mixed): bool $conditionFn */
        private readonly Closure $conditionFn,
        private bool $taken = false,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if ($this->taken) {
            return new Reduced($accumulator);
        }
        $accumulator = $this->inner->step($accumulator, $reducible);
        if (($this->conditionFn)($reducible)) {
            $this->taken = true;
            return new Reduced($accumulator);
        }
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
