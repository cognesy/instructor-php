<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanDeserializeJson;
use Cognesy\Instructor\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Contracts\CanSelfValidate;
use Cognesy\Instructor\Contracts\CanTransformResponse;
use Cognesy\Instructor\Contracts\CanValidateResponse;
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

class ResponseHandler
{
    private EventDispatcher $eventDispatcher;
    private CanDeserializeResponse $deserializer;
    private CanValidateResponse $validator;

    public function __construct(
        EventDispatcher        $eventDispatcher,
        CanDeserializeResponse $deserializer,
        CanValidateResponse    $validator,
    )
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->deserializer = $deserializer;
        $this->validator = $validator;
    }

    /**
     * Deserialize JSON and validate response object
     */
    public function toResponse(ResponseModel $responseModel, string $json) : Result {
        // ...deserialize
        $deserializationResult = $this->deserialize($responseModel, $json);
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
    protected function deserialize(ResponseModel $responseModel, string $json) : Result {
        if ($responseModel->instance instanceof CanDeserializeJson) {
            $this->eventDispatcher->dispatch(new CustomResponseDeserializationAttempt($responseModel->instance, $json));
            return Result::try(fn() => $responseModel->instance->fromJson($json));
        }
        // else - use standard deserializer
        $this->eventDispatcher->dispatch(new ResponseDeserializationAttempt($responseModel, $json));
        return Result::try(fn() => $this->deserializer->deserialize($json, $responseModel->class));
    }

    /**
     * Validate deserialized response object
     */
    protected function validate(object $response) : Result {
        if ($response instanceof CanSelfValidate) {
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
        if ($object instanceof CanTransformResponse) {
            $result = $object->transform();
            $this->eventDispatcher->dispatch(new ResponseTransformed($result));
            return $result;
        }
        return $object;
    }
}