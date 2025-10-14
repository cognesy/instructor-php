<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Reducers;

use Cognesy\Instructor\Partials\Data\AggregatedResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Stream\Contracts\Reducer;

class AggregateResponseReducer implements Reducer
{
    private AggregatedResponse $aggregate;

    public function __construct(
        private Reducer $inner,
    ) {
        $this->aggregate = AggregatedResponse::empty();
    }

    #[\Override]
    public function init(): mixed {
        $this->aggregate = AggregatedResponse::empty();
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialInferenceResponse);

        // Merge partial into rolling aggregate (O(1))
        $this->aggregate = $this->aggregate->merge($reducible);

        // Forward aggregate instead of partial
        return $this->inner->step($accumulator, $this->aggregate);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
