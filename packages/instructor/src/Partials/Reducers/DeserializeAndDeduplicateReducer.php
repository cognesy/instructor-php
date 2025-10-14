<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Reducers;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Partials\Data\PartialContext;
use Cognesy\Instructor\Streaming\PartialGen\AssemblePartialObject;
use Cognesy\Instructor\Streaming\PartialGen\PartialObject;
use Cognesy\Stream\Contracts\Reducer;

class DeserializeAndDeduplicateReducer implements Reducer
{
    private PartialObject $state;

    public function __construct(
        private Reducer $inner,
        private AssemblePartialObject $assembler,
        private ResponseModel $responseModel,
    ) {
        $this->state = PartialObject::empty();
    }

    #[\Override]
    public function init(): mixed {
        $this->state = PartialObject::empty();
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialContext);

        // If no JSON available (e.g., finish-only partial), forward unchanged
        if ($reducible->json === null || $reducible->json->isEmpty()) {
            return $this->inner->step($accumulator, $reducible);
        }

        // Use existing AssemblePartialObject (validation + deserialization + transformation)
        $this->state = $this->assembler->makeWith(
            state: $this->state,
            partialJson: $reducible->json,
            responseModel: $this->responseModel,
        );

        $result = $this->state->result();

        // Handle deserialization/validation errors
        if ($result->isFailure()) {
            $errorContext = $reducible->withError($result->error());
            // Still forward to next (allows error tracking transducer downstream)
            return $this->inner->step($accumulator, $errorContext);
        }

        $emittable = $this->state->emittable();

        // No new emittable value yet — forward context to allow downstream
        // components (e.g., usage aggregation) to observe this step
        if ($emittable === null) {
            return $this->inner->step($accumulator, $reducible);
        }

        // Success - mark for emission
        return $this->inner->step($accumulator, $reducible
            ->withObject($emittable)
            ->markForEmit(),
        );
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
