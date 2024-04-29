<?php

namespace Cognesy\Instructor\Core\Response;

use Cognesy\Instructor\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Response\CustomResponseDeserializationAttempt;
use Cognesy\Instructor\Events\Response\ResponseDeserializationAttempt;
use Cognesy\Instructor\Events\Response\ResponseDeserializationFailed;
use Cognesy\Instructor\Events\Response\ResponseDeserialized;
use Cognesy\Instructor\Utils\Result;

class ResponseDeserializer
{
    public function __construct(
        private EventDispatcher $events,
        private CanDeserializeClass $deserializer,
    ) {}

    public function deserialize(string $json, ResponseModel $responseModel) : Result {
        $result = match(true) {
            $responseModel->instance instanceof CanDeserializeSelf => $this->deserializeSelf($json, $responseModel->instance),
            default => $this->deserializeAny($json, $responseModel)
        };
        $this->events->dispatch(match(true) {
            $result->isFailure() => new ResponseDeserializationFailed($result->errorMessage()),
            default => new ResponseDeserialized($result->unwrap())
        });
        return $result;
    }

    protected function deserializeSelf(string $json, CanDeserializeSelf $response) : Result {
        $this->events->dispatch(new CustomResponseDeserializationAttempt($response, $json));
        return Result::try(fn() => $response->fromJson($json));
    }

    protected function deserializeAny(string $json, ResponseModel $responseModel) : Result {
        $this->events->dispatch(new ResponseDeserializationAttempt($responseModel, $json));
        return Result::try(fn() => $this->deserializer->fromJson($json, $responseModel->class));
    }
}
