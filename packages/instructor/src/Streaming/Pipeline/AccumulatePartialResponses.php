<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming\Pipeline;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Stateful transducer that keeps streaming extraction/parsing state internally.
 *
 * It replaces the frame-based extract -> deserialize -> enrich chain for the
 * hot streaming path. Accumulation ownership lives in StructuredOutputStreamState
 * and the reducer forwards the mutable state object downstream.
 */
final readonly class AccumulatePartialResponses implements Transducer
{
    public function __construct(
        private OutputMode $mode,
        private CanDeserializeResponse $deserializer,
        private CanTransformResponse $transformer,
        private ResponseModel $responseModel,
        private int $materializationInterval = 1,
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new AccumulatePartialResponsesReducer(
            inner: $reducer,
            mode: $this->mode,
            deserializer: $this->deserializer,
            transformer: $this->transformer,
            responseModel: $this->responseModel,
            materializationInterval: $this->materializationInterval,
        );
    }
}
