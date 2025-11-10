<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Clean\Pipeline;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Track sequence updates without emitting events.
 *
 * Transducer that creates UpdateSequenceReducer.
 */
final readonly class UpdateSequence implements Transducer
{
    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new UpdateSequenceReducer(
            inner: $reducer,
        );
    }
}
