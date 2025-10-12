<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Decorators\DistinctReducer;

final readonly class DistinctBy implements Transducer
{
    /**
     * @param Closure(mixed): (string|int) $keyFn
     */
    public function __construct(private Closure $keyFn) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new DistinctReducer(
            reducer: $reducer,
            keyFn: $this->keyFn,
        );
    }
}