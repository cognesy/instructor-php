<?php

namespace Cognesy\Instructor\Core\Response;

use Cognesy\Instructor\Contracts\CanHandleResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Utils\Result;

class ResponseHandler implements CanHandleResponse
{

    public function __construct(
        private ResponseDeserializer $responseDeserializer,
        private ResponseValidator $responseValidator,
        private ResponseTransformer $responseTransformer,
    ) {}

    /**
     * Deserialize JSON, validate and transform response
     */
    public function toResponse(string $jsonData, ResponseModel $responseModel) : Result {
        // ...deserialize
        $result = $this->responseDeserializer->deserialize($jsonData, $responseModel);
        if ($result->isFailure()) {
            return $result;
        }
        $object = $result->value();

        // ...validate
        $result = $this->responseValidator->validate($object);
        if ($result->isFailure()) {
            return $result;
        }

        // ...transform
        $result = $this->responseTransformer->transform($object);
        if ($result->isFailure()) {
            return $result;
        }
        return $result;
    }
}