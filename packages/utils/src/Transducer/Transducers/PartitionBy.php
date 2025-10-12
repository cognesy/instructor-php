<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Closure;
use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\PartitionByReducer;

final readonly class PartitionBy implements Transducer
{
    /**
     * @param Closure(mixed): (string|int) $getGroupFn
     */
    public function __construct(private Closure $getGroupFn) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new PartitionByReducer($this->getGroupFn, $reducer);
    }
}