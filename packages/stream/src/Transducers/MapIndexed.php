<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Decorators\MapIndexedReducer;

final readonly class MapIndexed implements Transducer
{
    /**
     * @param Closure(mixed, int): mixed $mapFn
     */
    public function __construct(private Closure $mapFn) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new MapIndexedReducer($this->mapFn, $reducer);
    }
}
