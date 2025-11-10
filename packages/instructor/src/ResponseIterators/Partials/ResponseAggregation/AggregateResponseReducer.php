<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Partials\ResponseAggregation;

use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Stream\Contracts\Reducer;

class AggregateResponseReducer implements Reducer
{
    private AggregationState $aggregate;

    public function __construct(
        private Reducer $inner,
        private bool $accumulatePartials = false,
    ) {
        $this->aggregate = AggregationState::empty();
    }

    #[\Override]
    public function init(): mixed {
        $this->aggregate = AggregationState::empty();
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialInferenceResponse);

        // Merge partial into rolling aggregate (O(1))
        $this->aggregate = $this->aggregate->merge($reducible);
        if ($this->accumulatePartials) {
            $this->aggregate = $this->aggregate->withPartialAppended($reducible);
        }

        // Forward aggregate instead of partial
        return $this->inner->step($accumulator, $this->aggregate);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
