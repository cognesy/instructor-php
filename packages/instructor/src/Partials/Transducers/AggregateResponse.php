<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Transducers;

use Cognesy\Instructor\Partials\Reducers\AggregateResponseReducer;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Maintains rolling aggregate of stream state.
 * Enables O(1) memory streaming with full observability.
 *
 * Input:  PartialInferenceResponse
 * Output: AggregatedResponse<TValue>
 * State:  AggregatedResponse (rolling accumulator)
 *
 * @template TValue
 */
final readonly class AggregateResponse implements Transducer
{
    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new AggregateResponseReducer(
            inner: $reducer
        );
    }
}
