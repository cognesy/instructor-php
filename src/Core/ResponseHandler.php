<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanDeserializeJson;
use Cognesy\Instructor\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Contracts\CanSelfValidate;
use Cognesy\Instructor\Contracts\CanValidateResponse;
use Cognesy\Instructor\Events\RequestHandler\ValidationRecoveryLimitReached;
use Cognesy\Instructor\Events\ResponseHandler\CustomResponseDeserializationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\CustomResponseValidationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\ResponseDeserializationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\ResponseDeserializationFailed;
use Cognesy\Instructor\Events\ResponseHandler\ResponseDeserialized;
use Cognesy\Instructor\Events\ResponseHandler\ResponseValidated;
use Cognesy\Instructor\Events\ResponseHandler\ResponseValidationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\ResponseValidationFailed;

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
    public function toResponse(ResponseModel $responseModel, string $json) : array {
        try {
            $object = $this->deserialize($responseModel, $json);
        } catch (\Exception $e) {
            $this->eventDispatcher->dispatch(new ResponseDeserializationFailed($e->getMessage()));
            return [null, $e->getMessage()];
        }
        $this->eventDispatcher->dispatch(new ResponseDeserialized($object));
        if ($this->validate($object)) {
            $this->eventDispatcher->dispatch(new ResponseValidated($object));
            return [$object, null];
        }
        $this->eventDispatcher->dispatch(new ResponseValidationFailed($this->errors()));
        return [null, $this->errors()];
    }

    /**
     * Deserialize response JSON
     */
    protected function deserialize(ResponseModel $responseModel, string $json) : mixed {
        if ($responseModel->instance instanceof CanDeserializeJson) {
            $this->eventDispatcher->dispatch(new CustomResponseDeserializationAttempt(
                $responseModel->instance,
                $json
            ));
            return $responseModel->instance->fromJson($json);
        }
        // else - use standard deserializer
        $this->eventDispatcher->dispatch(new ResponseDeserializationAttempt($responseModel, $json));
        return $this->deserializer->deserialize($json, $responseModel->class);
    }

    /**
     * Validate deserialized response object
     */
    protected function validate(object $response) : bool {
        if ($response instanceof CanSelfValidate) {
            $this->eventDispatcher->dispatch(new CustomResponseValidationAttempt($response));
            return $response->validate();
        }
        // else - use standard validator
        $this->eventDispatcher->dispatch(new ResponseValidationAttempt($response));
        return $this->validator->validate($response);
    }

    /**
     * Get validation errors
     */
    public function errors() : string {
        return $this->validator->errors();
    }
}