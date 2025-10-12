<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Closure;
use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\DistinctReducer;

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