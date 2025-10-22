<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Partials\PartialCreation;

use Cognesy\Instructor\Config\PartialsGeneratorConfig;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Executors\Partials\ContentMode\PartialJson;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\PartialValidation;
use Cognesy\Utils\Result\Result;
use Throwable;

final class PartialAssembler
{
    private PartialValidation $validation;

    public function __construct(
        private ResponseDeserializer $deserializer,
        private ResponseTransformer $transformer,
        private PartialsGeneratorConfig $config,
    ) {
        $this->validation = new PartialValidation();
    }

    public function makeWith(
        PartialObjectState $state,
        PartialJson $partialJson,
        ResponseModel $responseModel,
    ): PartialObjectState {
        $normalized = $partialJson->normalized();

        try {
            $validationResult = $this->validation->validatePartialResponse(
                $normalized,
                $responseModel,
                $this->config,
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

        $transformed = $this->transformer->transform($deserialized->unwrap());
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

