<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Contracts\CanHandleResponse;
use Cognesy\Instructor\Contracts\CanValidateSelf;
use Cognesy\Instructor\Contracts\CanTransformSelf;
use Cognesy\Instructor\Contracts\CanValidateObject;
use Cognesy\Instructor\Events\ResponseHandler\CustomResponseDeserializationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\CustomResponseValidationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\ResponseDeserializationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\ResponseDeserializationFailed;
use Cognesy\Instructor\Events\ResponseHandler\ResponseDeserialized;
use Cognesy\Instructor\Events\ResponseHandler\ResponseTransformed;
use Cognesy\Instructor\Events\ResponseHandler\ResponseValidated;
use Cognesy\Instructor\Events\ResponseHandler\ResponseValidationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\ResponseValidationFailed;
use Cognesy\Instructor\Utils\Result;

class ResponseHandler implements CanHandleResponse
{
    private EventDispatcher $eventDispatcher;
    private CanDeserializeClass $deserializer;
    private CanValidateObject $validator;

    public function __construct(
        EventDispatcher     $eventDispatcher,
        CanDeserializeClass $deserializer,
        CanValidateObject   $validator,
    )
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->deserializer = $deserializer;
        $this->validator = $validator;
    }

    /**
     * Deserialize JSON and validate response object
     */
    public function toResponse(string $jsonData, ResponseModel $responseModel) : Result {
        // ...deserialize
        $deserializationResult = $this->deserialize($jsonData, $responseModel);
        if ($deserializationResult->isFailure()) {
            $this->eventDispatcher->dispatch(new ResponseDeserializationFailed($deserializationResult->errorMessage()));
            return $deserializationResult;
        }
        $object = $deserializationResult->value();
        $this->eventDispatcher->dispatch(new ResponseDeserialized($object));

        // ...validate
        $validationResult = $this->validate($object);
        if ($validationResult->isFailure()) {
            $this->eventDispatcher->dispatch(new ResponseValidationFailed($validationResult->errorValue()));
            return $validationResult;
        }
        $this->eventDispatcher->dispatch(new ResponseValidated($object));

        // ...transform
        $transformedObject = $this->transform($object);

        return Result::success($transformedObject);
    }

    /**
     * Deserialize response JSON
     */
    protected function deserialize(string $json, ResponseModel $responseModel) : Result {
        if ($responseModel->instance instanceof CanDeserializeSelf) {
            $this->eventDispatcher->dispatch(new CustomResponseDeserializationAttempt($responseModel->instance, $json));
            return Result::try(fn() => $responseModel->instance->fromJson($json));
        }
        // else - use standard deserializer
        $this->eventDispatcher->dispatch(new ResponseDeserializationAttempt($responseModel, $json));
        return Result::try(fn() => $this->deserializer->fromJson($json, $responseModel->class));
    }

    /**
     * Validate deserialized response object
     */
    protected function validate(object $response) : Result {
        if ($response instanceof CanValidateSelf) {
            $this->eventDispatcher->dispatch(new CustomResponseValidationAttempt($response));
            $errors = $response->validate();
            return match(count($errors)) {
                0 => Result::success($response),
                default => Result::failure($errors)
            };
        }
        // else - use standard validator
        $this->eventDispatcher->dispatch(new ResponseValidationAttempt($response));
        $errors = $this->validator->validate($response);
        return match(count($errors)) {
            0 => Result::success($response),
            default => Result::failure($errors)
        };
    }

    /**
     * Transform response object
     */
    protected function transform(object $object) : mixed {
        if ($object instanceof CanTransformSelf) {
            $result = $object->transform();
            $this->eventDispatcher->dispatch(new ResponseTransformed($result));
            return $result;
        }
        return $object;
    }
}