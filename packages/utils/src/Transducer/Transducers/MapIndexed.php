<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Closure;
use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\MapIndexedReducer;

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
