<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Limit\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Limit\Decorators\TakeUntilReducer;

final readonly class TakeUntil implements Transducer
{
    /**
     * @param Closure(mixed): bool $conditionFn
     */
    public function __construct(
        private Closure $conditionFn
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new TakeUntilReducer(
            inner: $reducer,
            conditionFn: $this->conditionFn
        );
    }
}