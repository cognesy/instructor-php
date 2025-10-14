<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Filter\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Filter\Decorators\KeepReducer;

final readonly class Keep implements Transducer
{
    /**
     * @param Closure(mixed): mixed $mapFn
     */
    public function __construct(private Closure $mapFn) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new KeepReducer(
            inner: $reducer,
            mapFn: $this->mapFn,
        );
    }
}
