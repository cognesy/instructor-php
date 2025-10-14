<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Group\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Group\Decorators\PartitionByReducer;

final readonly class PartitionBy implements Transducer
{
    /**
     * @param Closure(mixed): (string|int) $getGroupFn
     */
    public function __construct(private Closure $getGroupFn) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new PartitionByReducer(
            inner: $reducer,
            getGroupFn: $this->getGroupFn,
        );
    }
}