<?php

namespace Cognesy\Instructor\Deserialization;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Response\CustomResponseDeserializationAttempt;
use Cognesy\Instructor\Events\Response\ResponseDeserializationAttempt;
use Cognesy\Instructor\Events\Response\ResponseDeserializationFailed;
use Cognesy\Instructor\Events\Response\ResponseDeserialized;
use Cognesy\Instructor\Utils\Result;
use Exception;

class ResponseDeserializer
{
    public function __construct(
        private EventDispatcher $events,
        /** @var CanDeserializeClass $deserializer */
        private array $deserializers,
    ) {}

    public function deserialize(string $json, ResponseModel $responseModel, string $toolName = null) : Result {
        $result = match(true) {
            $responseModel->instance() instanceof CanDeserializeSelf => $this->deserializeSelf($json, $responseModel->instance(), $toolName),
            default => $this->deserializeAny($json, $responseModel)
        };
        $this->events->dispatch(match(true) {
            $result->isFailure() => new ResponseDeserializationFailed($result->errorMessage()),
            default => new ResponseDeserialized($result->unwrap())
        });
        return $result;
    }

    protected function deserializeSelf(string $json, CanDeserializeSelf $response, string $toolName = null) : Result {
        $this->events->dispatch(new CustomResponseDeserializationAttempt($response, $json));
        return Result::try(fn() => $response->fromJson($json, $toolName));
    }

    protected function deserializeAny(string $json, ResponseModel $responseModel) : Result {
        $this->events->dispatch(new ResponseDeserializationAttempt($responseModel, $json));
        foreach ($this->deserializers as $deserializer) {
            $deserializer = match(true) {
                is_string($deserializer) && is_subclass_of($deserializer, CanDeserializeClass::class) => new $deserializer(),
                $deserializer instanceof CanDeserializeClass => $deserializer,
                default => throw new Exception('Deserializer must implement CanDeserializeClass interface'),
            };
            $result = Result::try(fn() => $deserializer->fromJson($json, $responseModel->returnedClass()));
            if ($result->isSuccess()) {
                return $result;
            }
        }
        return Result::failure('No deserializer found for the response');
    }
}
