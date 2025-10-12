<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Support\CallableReducer;
use Throwable;

final readonly class TryCatch implements Transducer
{
    /**
     * @param Closure(mixed): mixed $tryFn
     * @param Closure(\Throwable, mixed): mixed|null $onError
     */
    public function __construct(
        private Closure $tryFn,
        private ?Closure $onError = null,
    ) {}

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
        return function(mixed $accumulator, mixed $reducible) use ($reducer): mixed {
            try {
                $result = ($this->tryFn)($reducible);
                return $reducer->step($accumulator, $result);
            } catch (Throwable $e) {
                if ($this->onError !== null) {
                    $fallback = ($this->onError)($e, $reducible);
                    if ($fallback !== null) {
                        return $reducer->step($accumulator, $fallback);
                    }
                }
                // If no error handler or returns null, skip this element
                return $accumulator;
            }
        };
    }
}
