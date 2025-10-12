<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Closure;
use Cognesy\Utils\Transducer\CallableReducer;
use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;

final readonly class Tap implements Transducer
{
    /**
     * @param Closure(mixed): void $sideEffectFn
     */
    public function __construct(private Closure $sideEffectFn) {}

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
    private function makeStepFunction(Reducer $reducer): Closure {
        return function (mixed $accumulator, mixed $input)
            use ($reducer) : mixed
        {
            ($this->sideEffectFn)($input);
            return $reducer->step($accumulator, $input);
        };
    }
}
