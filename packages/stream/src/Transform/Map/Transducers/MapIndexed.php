<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Map\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Map\Decorators\MapIndexedReducer;

final readonly class MapIndexed implements Transducer
{
    /**
     * @param Closure(mixed, int): mixed $mapFn
     */
    public function __construct(private Closure $mapFn) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new MapIndexedReducer($reducer, $this->mapFn);
    }
}
