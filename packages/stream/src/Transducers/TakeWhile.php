<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Stream\Support\Reduced;

final readonly class TakeWhile implements Transducer
{
    /**
     * @param Closure(mixed): bool $conditionFn
     */
    public function __construct(private Closure $conditionFn) {}

    #[\Override]
    public function __invoke(Reducer $reducer) : Reducer {
        return new CallableReducer(
            stepFn: $this->makeStepFunction($reducer),
            completeFn: $reducer->complete(...),
            initFn: $reducer->init(...),
        );
    }

    /**
     * @return Closure(mixed, mixed): mixed
     */
    private function makeStepFunction(Reducer $reducer) : Closure {
        return function(mixed $accumulator, mixed $reducible)
            use ($reducer) : mixed
        {
            if (($this->conditionFn)($reducible)) {
                return $reducer->step($accumulator, $reducible);
            }
            return new Reduced($accumulator);
        };
    }
}