<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Closure;
use Cognesy\Utils\Transducer\CallableReducer;
use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;

final readonly class Map implements Transducer
{
    /**
     * @param Closure(mixed): mixed $mapFn
     */
    public function __construct(private Closure $mapFn) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
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
        return function (mixed $accumulator, mixed $reducible)
            use ($reducer) : mixed
        {
            return $reducer->step($accumulator, ($this->mapFn)($reducible));
        };
    }
}