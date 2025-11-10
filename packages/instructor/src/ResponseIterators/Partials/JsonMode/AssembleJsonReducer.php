<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Partials\JsonMode;

use Cognesy\Instructor\ResponseIterators\Partials\DeltaExtraction\PartialProcessingState;
use Cognesy\Stream\Contracts\Reducer;

class AssembleJsonReducer implements Reducer
{
    private PartialJson $state;

    public function __construct(
        private Reducer $inner,
    ) {
        $this->state = PartialJson::initial();
    }

    #[\Override]
    public function init(): mixed {
        $this->state = PartialJson::initial();
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialProcessingState);

        // Accumulate delta into JSON
        $this->state = $this->state->assemble($reducible->delta);

        // Skip if JSON is empty, unless finishReason present OR driver provided value.
        if ($this->state->isEmpty()) {
            if ($reducible->response->finishReason !== '' || $reducible->response->hasValue()) {
                // Forward unchanged so downstream can emit/aggregate based on existing value/usage.
                return $this->inner->step($accumulator, $reducible);
            }
            return $accumulator;
        }

        // Forward with updated JSON
        return $this->inner->step($accumulator, $reducible->withJson($this->state));
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
