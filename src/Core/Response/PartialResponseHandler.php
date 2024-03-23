<?php

namespace Cognesy\Instructor\Core\Response;

use Cognesy\Instructor\Contracts\CanHandlePartialResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Utils\Result;

class PartialResponseHandler implements CanHandlePartialResponse
{
    public function __construct(
        private ResponseDeserializer $responseDeserializer,
        private ResponseTransformer $responseTransformer,
    ) {}

    public function toPartialResponse(string $jsonData, ResponseModel $responseModel): Result
    {
        // ...deserialize
        $result = $this->responseDeserializer->deserialize($jsonData, $responseModel);
        if ($result->isFailure()) {
            return $result;
        }
        $object = $result->unwrap();

        // ...transform
        $result = $this->responseTransformer->transform($object);
        if ($result->isFailure()) {
            return $result;
        }

        return $result;
    }
}