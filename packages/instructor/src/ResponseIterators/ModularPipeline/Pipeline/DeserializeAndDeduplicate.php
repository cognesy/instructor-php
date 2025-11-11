<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Pipeline;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidatePartialResponse;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Deserialize, validate, transform, and deduplicate partial objects.
 *
 * Transducer that creates DeserializeAndDeduplicateReducer.
 */
final readonly class DeserializeAndDeduplicate implements Transducer
{
    public function __construct(
        private CanDeserializeResponse $deserializer,
        private CanValidatePartialResponse $validator,
        private CanTransformResponse $transformer,
        private ResponseModel $responseModel,
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new DeserializeAndDeduplicateReducer(
            inner: $reducer,
            deserializer: $this->deserializer,
            validator: $this->validator,
            transformer: $this->transformer,
            responseModel: $this->responseModel,
        );
    }
}
