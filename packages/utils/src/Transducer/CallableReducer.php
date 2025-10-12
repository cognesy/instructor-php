<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer;

use Closure;
use Cognesy\Utils\Transducer\Contracts\Reducer;

/**
 * A reducer that uses callables for its step,
 * complete, and init methods.
 */
final class CallableReducer implements Reducer
{
    /**
     * @param Closure(mixed, mixed): mixed $stepFn
     * @param Closure(mixed): mixed|null $completeFn
     * @param Closure(): mixed|null $initFn
     */
    public function __construct(
        private Closure $stepFn,
        private ?Closure $completeFn = null,
        private ?Closure $initFn = null,
    ) {}

    #[\Override]
    public function init(): mixed {
        return match(true) {
            ($this->initFn === null) => null,
            default => ($this->initFn)(),
        };
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        return ($this->stepFn)($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return match(true) {
            ($this->completeFn === null) => $accumulator,
            default => ($this->completeFn)($accumulator),
        };
    }
}