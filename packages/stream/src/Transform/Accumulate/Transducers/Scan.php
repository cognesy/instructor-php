<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Accumulate\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Accumulate\Decorators\ScanReducer;

final readonly class Scan implements Transducer
{
    /**
     * @param Closure(mixed, mixed): mixed $scanFn
     */
    public function __construct(
        private Closure $scanFn,
        private mixed $initialState,
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new ScanReducer(
            inner: $reducer,
            scanFn: $this->scanFn,
            state: $this->initialState
        );
    }
}
