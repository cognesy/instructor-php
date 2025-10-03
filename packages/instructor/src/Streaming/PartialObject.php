<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\PartialValidationPolicy;
use Cognesy\Utils\Result\Result;
use Throwable;

final class PartialObject
{
    public function __construct(
        private PartialHash $hash
    ) {}

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function empty(): self {
        return new self(PartialHash::empty());
    }

    // MUTATORS ////////////////////////////////////////////////

    public function update(
        PartialJson $partialJson,
        ResponseModel $responseModel,
        ResponseDeserializer $deserializer,
        ResponseTransformer $transformer,
        PartialValidationPolicy $validation,
        string $toolName,
        bool $preventJsonSchema,
        bool $matchToExpectedFields,
    ): PartialObjectUpdate {
        $normalized = $partialJson->normalized();

        // validate early; convert thrown exceptions into Result failures locally
        try {
            $validationResult = $validation->validatePartialResponse(
                $normalized,
                $responseModel,
                $preventJsonSchema,
                $matchToExpectedFields,
            );
        } catch (Throwable $e) {
            $failure = Result::failure($e->getMessage());
            return new PartialObjectUpdate($this, null, $failure);
        }

        if ($validationResult->isFailure()) {
            return new PartialObjectUpdate($this, null, $validationResult);
        }

        // deserialize
        $deserialized = $deserializer->deserialize($normalized, $responseModel, $toolName);
        if ($deserialized->isFailure()) {
            return new PartialObjectUpdate($this, null, $deserialized);
        }

        // transform
        $transformed = $transformer->transform($deserialized->unwrap());
        if ($transformed->isFailure()) {
            return new PartialObjectUpdate($this, null, $transformed);
        }
        $object = $transformed->unwrap();

        // dedupe
        if (!$this->hash->shouldEmitFor($object)) {
            return new PartialObjectUpdate($this, null, Result::failure('No changes detected'));
        }

        $next = new self($this->hash->updatedWith($object));
        return new PartialObjectUpdate($next, $object, Result::success($object));
    }
}
