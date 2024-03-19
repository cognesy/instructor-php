<?php

namespace Cognesy\Instructor\Core\Response;

use Cognesy\Instructor\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\ResponseHandler\CustomResponseDeserializationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\ResponseDeserializationAttempt;
use Cognesy\Instructor\Utils\Result;

class ResponseDeserializer
{
    public function __construct(
        private EventDispatcher $eventDispatcher,
        private CanDeserializeClass $deserializer,
    ) {}

    public function deserialize(string $json, ResponseModel $responseModel) : Result {
        return match(true) {
            $responseModel->instance instanceof CanDeserializeSelf => $this->deserializeSelf($json, $responseModel->instance),
            default => $this->deserializeAny($json, $responseModel)
        };
    }

    protected function deserializeSelf(string $json, CanDeserializeSelf $response) : Result {
        $this->eventDispatcher->dispatch(new CustomResponseDeserializationAttempt($response, $json));
        return Result::try(fn() => $response->fromJson($json));
    }

    protected function deserializeAny(string $json, ResponseModel $responseModel) : Result {
        $this->eventDispatcher->dispatch(new ResponseDeserializationAttempt($responseModel, $json));
        return Result::try(fn() => $this->deserializer->fromJson($json, $responseModel->class));
    }
}
