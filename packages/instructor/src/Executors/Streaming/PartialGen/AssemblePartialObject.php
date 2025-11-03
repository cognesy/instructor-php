<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Streaming\PartialGen;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidatePartialResponse;
use Cognesy\Utils\Result\Result;
use Throwable;

final class AssemblePartialObject
{
    public function __construct(
        private CanDeserializeResponse $deserializer,
        private CanValidatePartialResponse $validator,
        private CanTransformResponse $transformer,
    ) {}

    public function makeWith(
        PartialObject $state,
        PartialJson $partialJson,
        ResponseModel $responseModel,
    ): PartialObject {
        $normalized = $partialJson->normalized();

        try {
            $validationResult = $this->validator->validatePartialResponse(
                $normalized,
                $responseModel,
            );
        } catch (Throwable $e) {
            $failure = Result::failure($e->getMessage());
            return $state->with($state->hash(), null, $failure);
        }

        if ($validationResult->isFailure()) {
            return $state->with($state->hash(), null, $validationResult);
        }

        $deserialized = $this->deserializer->deserialize($normalized, $responseModel);
        if ($deserialized->isFailure()) {
            return $state->with($state->hash(), null, $deserialized);
        }

        $transformed = $this->transformer->transform($deserialized->unwrap(), $responseModel);
        if ($transformed->isFailure()) {
            return $state->with($state->hash(), null, $transformed);
        }
        $object = $transformed->unwrap();

        if (!$state->hash()->shouldEmitFor($object)) {
            return $state->with($state->hash(), null, Result::success(null));
        }

        $nextHash = $state->hash()->updatedWith($object);
        return $state->with($nextHash, $object, Result::success($object));
    }
}

