<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Partials\PartialEmission;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Convert PartialContext back to PartialInferenceResponse.
 *
 * Input:  PartialContext
 * Output: PartialInferenceResponse (if shouldEmit)
 * State:  Stateless
 */
final readonly class EnrichResponse implements Transducer
{
    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new EnrichResponseReducer(inner: $reducer);
    }
}
