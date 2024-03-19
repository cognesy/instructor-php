<?php

namespace Cognesy\Instructor\Core\Response;

use Cognesy\Instructor\Contracts\CanHandlePartialResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\ResponseHandler\ResponseDeserializationFailed;
use Cognesy\Instructor\Events\ResponseHandler\ResponseDeserialized;
use Cognesy\Instructor\Utils\Result;

class PartialResponseHandler implements CanHandlePartialResponse
{
    public function __construct(
        private EventDispatcher $eventDispatcher,
        private ResponseDeserializer $responseDeserializer,
        private ResponseTransformer $responseTransformer,
    ) {}

    public function toPartialResponse(string $jsonData, ResponseModel $responseModel): Result
    {
        // ...deserialize
        $result = $this->responseDeserializer->deserialize($jsonData, $responseModel);
        if ($result->isFailure()) {
            $this->eventDispatcher->dispatch(new ResponseDeserializationFailed($result->errorMessage()));
            return $result;
        }
        $object = $result->value();
        $this->eventDispatcher->dispatch(new ResponseDeserialized($object));

        // ...transform
        $transformedObject = $this->responseTransformer->transform($object);

        return Result::success($transformedObject);
    }
}