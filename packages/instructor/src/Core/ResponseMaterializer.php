<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Data\ResponseFailure;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Enums\ResponseFailureStage;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidateResponse;
use Cognesy\Schema\Validation\SchemaDataValidator;
use Cognesy\Utils\Result\Result;
use Throwable;

/**
 * Materializes extracted response data through one final pipeline.
 *
 * Streaming previews deserialize only. A completed stream retains its
 * extracted data and sends it through materialize(), exactly like a
 * synchronous response. Pre-valued driver deltas are accepted as an explicit
 * compatibility input and still receive schema/object validation and final
 * transformation.
 */
final readonly class ResponseMaterializer
{
    public function __construct(
        private CanDeserializeResponse $deserializer,
        private CanValidateResponse $validator,
        private CanTransformResponse $transformer,
    ) {}

    public function materialize(mixed $input, ResponseModel $responseModel): Result
    {
        $schemaValidation = $this->validateSchema($input, $responseModel);
        if ($schemaValidation->isFailure()) {
            return $schemaValidation;
        }

        $deserialized = $this->deserializeStage($input, $responseModel);
        if ($deserialized->isFailure()) {
            return $deserialized;
        }

        $validated = $this->validateObjectStage($deserialized->unwrap(), $responseModel);
        if ($validated->isFailure()) {
            return $validated;
        }

        return $this->transformStage($validated->unwrap(), $responseModel);
    }

    public function preview(mixed $input, ResponseModel $responseModel): Result
    {
        return $this->deserializeStage($input, $responseModel);
    }

    private function validateSchema(mixed $input, ResponseModel $responseModel): Result
    {
        try {
            $schemaInput = $this->schemaValidationInput($input);
            $validation = (new SchemaDataValidator($responseModel->schema()))->validate($schemaInput);
            return match (true) {
                $validation->isInvalid() => $this->failure(
                    ResponseFailureStage::SchemaValidation,
                    $validation->getErrorMessage(),
                    $responseModel,
                ),
                default => Result::success($input),
            };
        } catch (Throwable $error) {
            return $this->failure(ResponseFailureStage::SchemaValidation, $error, $responseModel);
        }
    }

    private function deserializeStage(mixed $input, ResponseModel $responseModel): Result
    {
        try {
            $result = $this->deserialize($input, $responseModel);
            return match (true) {
                $result->isFailure() => $this->failure(
                    ResponseFailureStage::Deserialization,
                    $result->error(),
                    $responseModel,
                ),
                default => $result,
            };
        } catch (Throwable $error) {
            return $this->failure(ResponseFailureStage::Deserialization, $error, $responseModel);
        }
    }

    private function validateObjectStage(mixed $value, ResponseModel $responseModel): Result
    {
        if (!is_object($value)) {
            return Result::success($value);
        }

        try {
            $result = $this->validator->validate($value, $responseModel);
            return match (true) {
                $result->isFailure() => $this->failure(
                    ResponseFailureStage::ObjectValidation,
                    $result->error(),
                    $responseModel,
                ),
                default => $result,
            };
        } catch (Throwable $error) {
            return $this->failure(ResponseFailureStage::ObjectValidation, $error, $responseModel);
        }
    }

    private function transformStage(mixed $value, ResponseModel $responseModel): Result
    {
        try {
            $result = $this->transformer->transform($value, $responseModel);
            return match (true) {
                $result->isFailure() => $this->failure(
                    ResponseFailureStage::Transformation,
                    $result->error(),
                    $responseModel,
                ),
                default => $result,
            };
        } catch (Throwable $error) {
            return $this->failure(ResponseFailureStage::Transformation, $error, $responseModel);
        }
    }

    private function deserialize(mixed $input, ResponseModel $responseModel): Result
    {
        if (is_array($input)) {
            return $this->deserializer->deserialize($input, $responseModel);
        }

        if ($this->matchesTarget($input, $responseModel)) {
            return Result::success($input);
        }

        return Result::failure(sprintf(
            'Pre-valued response of type %s does not match output target %s.',
            get_debug_type($input),
            $this->targetDescription($responseModel),
        ));
    }

    private function matchesTarget(mixed $value, ResponseModel $responseModel): bool
    {
        $outputFormat = $responseModel->outputFormat();
        if ($outputFormat->isArray()) {
            return is_array($value);
        }

        $targetClass = $outputFormat->targetClass();
        return $targetClass !== null && $value instanceof $targetClass;
    }

    private function targetDescription(ResponseModel $responseModel): string
    {
        $outputFormat = $responseModel->outputFormat();
        return match (true) {
            $outputFormat->isArray() => 'array',
            $outputFormat->targetClass() !== null => $outputFormat->targetClass(),
            default => $outputFormat->type->value,
        };
    }

    private function schemaValidationInput(mixed $input): mixed
    {
        return match (true) {
            $input instanceof Sequenceable => ['list' => $input->toArray()],
            default => $input,
        };
    }

    private function failure(
        ResponseFailureStage $stage,
        mixed $error,
        ResponseModel $responseModel,
    ): Result {
        return Result::failure(ResponseFailure::fromError(
            stage: $stage,
            error: $error,
            context: ['target' => $this->targetDescription($responseModel)],
        ));
    }
}
