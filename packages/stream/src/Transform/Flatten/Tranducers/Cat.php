<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Flatten\Tranducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Flatten\Decorators\CatReducer;

/**
 * Transducer that concatenates all inner iterables into
 * a single sequence.
 *
 * Example:
 * [[1, 2], [3, 4], [5]] => [1, 2, 3, 4, 5]
 */
final readonly class Cat implements Transducer
{
    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new CatReducer(
            inner: $reducer
        );
    }
}

