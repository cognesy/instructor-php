<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Partials\ResponseAggregation;

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
    public function __construct(
        private bool $accumulatePartials = false,
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new AggregateResponseReducer(
            inner: $reducer,
            accumulatePartials: $this->accumulatePartials,
        );
    }
}
