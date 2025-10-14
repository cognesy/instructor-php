<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Filter\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Filter\Decorators\FilterReducer;

final readonly class Filter implements Transducer
{
    /**
     * @param Closure(mixed): bool $conditionFn
     */
    public function __construct(private Closure $conditionFn) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new FilterReducer(
            inner: $reducer,
            conditionFn: $this->conditionFn,
        );
    }
}