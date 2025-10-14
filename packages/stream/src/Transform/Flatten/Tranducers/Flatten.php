<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Flatten\Tranducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Flatten\Decorators\FlattenReducer;

/**
 * Flattens a nested structure up to a specified depth.
 *
 * Example:
 * [1, [2, 3], [[4, 5]], 6] with depth 1 becomes [1, 2, 3, [4, 5], 6]
 * [1, [2, 3], [[4, 5]], 6] with depth 2 becomes [1, 2, 3, 4, 5, 6]
 */
final readonly class Flatten implements Transducer
{
    public function __construct(private int $depth = 1) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new FlattenReducer(
            inner: $reducer,
            depth: $this->depth,
        );
    }
}
