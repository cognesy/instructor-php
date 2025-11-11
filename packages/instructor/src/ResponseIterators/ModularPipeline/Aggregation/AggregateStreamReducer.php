<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Aggregation;

use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Stream\Contracts\Reducer;

/**
 * Terminal reducer that aggregates PartialInferenceResponse into StreamAggregate.
 *
 * This is the final stage of the pipeline, accumulating streaming data
 * into a rolling aggregate with O(1) memory.
 *
 * Yields StreamAggregate on each step for observation.
 */
final class AggregateStreamReducer implements Reducer
{
    private StreamAggregate $aggregate;

    public function __construct(
        private readonly bool $accumulatePartials = false,
    ) {
        $this->aggregate = StreamAggregate::empty($this->accumulatePartials);
    }

    #[\Override]
    public function init(): mixed {
        $this->aggregate = StreamAggregate::empty($this->accumulatePartials);
        return $this->aggregate;
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialInferenceResponse);

        // Merge partial into rolling aggregate
        $this->aggregate = $this->aggregate->merge($reducible);

        // Return aggregate as accumulator
        return $this->aggregate;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        // Return final aggregate
        return $this->aggregate;
    }
}
