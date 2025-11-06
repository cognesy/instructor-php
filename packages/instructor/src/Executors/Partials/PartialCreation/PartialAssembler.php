<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Partials\PartialCreation;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Executors\Partials\JsonMode\PartialJson;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidatePartialResponse;
use Cognesy\Utils\Result\Result;
use Throwable;

final class PartialAssembler
{
    private CanDeserializeResponse $deserializer;
    private CanValidatePartialResponse $validator;
    private CanTransformResponse $transformer;

    public function __construct(
        CanDeserializeResponse $deserializer,
        CanValidatePartialResponse $validator,
        CanTransformResponse $transformer,
    ) {
        $this->deserializer = $deserializer;
        $this->validator = $validator;
        $this->transformer = $transformer;
    }

    public function makeWith(
        PartialObjectState $state,
        PartialJson $partialJson,
        ResponseModel $responseModel,
    ): PartialObjectState {
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

