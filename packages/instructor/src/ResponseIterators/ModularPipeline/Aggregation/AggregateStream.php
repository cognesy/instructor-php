<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Aggregation;

use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Terminal transducer for stream aggregation.
 *
 * Creates AggregateStreamReducer.
 */
final readonly class AggregateStream implements Transducer
{
    public function __construct(
        private bool $accumulatePartials = false,
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        // Wrap the sink so we can forward the rolling aggregate downstream
        // and let the queue-based sink enqueue it for observation.
        $accumulate = $this->accumulatePartials;

        return new class($reducer, $accumulate) implements Reducer {
            private StreamAggregate $aggregate;

            public function __construct(
                private readonly Reducer $inner,
                private readonly bool $accumulatePartials,
            ) {
                $this->aggregate = StreamAggregate::empty($this->accumulatePartials);
            }

            #[\Override]
            public function init(): mixed {
                $this->aggregate = StreamAggregate::empty($this->accumulatePartials);
                return $this->inner->init();
            }

            #[\Override]
            public function step(mixed $accumulator, mixed $reducible): mixed {
                \assert($reducible instanceof PartialInferenceResponse);
                $this->aggregate = $this->aggregate->merge($reducible);
                // Forward current aggregate so the sink can enqueue it
                return $this->inner->step($accumulator, $this->aggregate);
            }

            #[\Override]
            public function complete(mixed $accumulator): mixed {
                return $this->inner->complete($accumulator);
            }
        };
    }
}
