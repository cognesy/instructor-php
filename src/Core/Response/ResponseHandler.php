<?php

namespace Cognesy\Instructor\Core\Response;

use Cognesy\Instructor\Contracts\CanHandleResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\ResponseHandler\ResponseDeserializationFailed;
use Cognesy\Instructor\Events\ResponseHandler\ResponseDeserialized;
use Cognesy\Instructor\Events\ResponseHandler\ResponseValidated;
use Cognesy\Instructor\Events\ResponseHandler\ResponseValidationFailed;
use Cognesy\Instructor\Utils\Result;

class ResponseHandler implements CanHandleResponse
{

    public function __construct(
        private EventDispatcher $eventDispatcher,
        private ResponseDeserializer $responseDeserializer,
        private ResponseValidator $responseValidator,
        private ResponseTransformer $responseTransformer,
    ) {}

    /**
     * Deserialize JSON, validate and transform response
     */
    public function toResponse(string $jsonData, ResponseModel $responseModel) : Result {
        // ...deserialize
        $deserializationResult = $this->responseDeserializer->deserialize($jsonData, $responseModel);
        if ($deserializationResult->isFailure()) {
            $this->eventDispatcher->dispatch(new ResponseDeserializationFailed($deserializationResult->errorMessage()));
            return $deserializationResult;
        }
        $object = $deserializationResult->value();
        $this->eventDispatcher->dispatch(new ResponseDeserialized($object));

        // ...validate
        $validationResult = $this->responseValidator->validate($object);
        if ($validationResult->isFailure()) {
            $this->eventDispatcher->dispatch(new ResponseValidationFailed($validationResult->error()));
            return $validationResult;
        }
        $this->eventDispatcher->dispatch(new ResponseValidated($object));

        // ...transform
        $transformedObject = $this->responseTransformer->transform($object);

        return Result::success($transformedObject);
    }
}