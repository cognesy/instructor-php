<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Enums\ReturnTarget;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidateResponse;
use Cognesy\Utils\Result\Result;
use Throwable;

/**
 * The single owner of hydration sequencing: how an extracted array (or an
 * already-built value) becomes the response value.
 *
 * - hydrate():        deserialize → validate → transform. Object-only stages
 *                     (validate/transform) are skipped for array return targets.
 * - hydratePartial(): deserialize → transform, NO validation — partials are
 *                     transient values built from incomplete JSON; validating
 *                     them would reject every mid-stream state.
 * - finalize():       validate → transform an already-built object (the
 *                     streaming prebuilt-value path).
 *
 * Emits no events itself — stage implementations (deserializer/validator/
 * transformer) own their observability; callers own failure reporting.
 */
final class ObjectHydrator
{
    public function __construct(
        private readonly CanDeserializeResponse $deserializer,
        private readonly CanValidateResponse $validator,
        private readonly CanTransformResponse $transformer,
    ) {}

    /**
     * @param array<array-key,mixed> $data
     */
    public function hydrate(array $data, ResponseModel $responseModel): Result {
        $skipObjectStages = $responseModel->returnTarget() === ReturnTarget::Array;

        try {
            $deserialized = $this->deserializer->deserialize($data, $responseModel);
            if ($deserialized->isFailure() || $skipObjectStages) {
                return $deserialized;
            }

            $validated = $this->validator->validate($deserialized->unwrap(), $responseModel);
            if ($validated->isFailure()) {
                return $validated;
            }

            return $this->transformer->transform($validated->unwrap(), $responseModel);
        } catch (Throwable $error) {
            return Result::failure($error);
        }
    }

    /**
     * @param array<array-key,mixed> $data
     */
    public function hydratePartial(array $data, ResponseModel $responseModel): Result {
        try {
            $deserialized = $this->deserializer->deserialize($data, $responseModel);
            if ($deserialized->isFailure()) {
                return $deserialized;
            }

            return $this->transformer->transform($deserialized->unwrap(), $responseModel);
        } catch (Throwable $error) {
            return Result::failure($error);
        }
    }

    public function finalize(mixed $value, ResponseModel $responseModel): Result {
        if (!$responseModel->returnTarget()->expectsObject() || !is_object($value)) {
            return Result::success($value);
        }

        try {
            $validated = $this->validator->validate($value, $responseModel);
            if ($validated->isFailure()) {
                return $validated;
            }

            return $this->transformer->transform($validated->unwrap(), $responseModel);
        } catch (Throwable $error) {
            return Result::failure($error);
        }
    }
}
