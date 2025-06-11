<?php

namespace Cognesy\Instructor\Deserialization;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Events\Response\CustomResponseDeserializationAttempt;
use Cognesy\Instructor\Events\Response\ResponseDeserializationAttempt;
use Cognesy\Instructor\Events\Response\ResponseDeserializationFailed;
use Cognesy\Instructor\Events\Response\ResponseDeserialized;
use Cognesy\Utils\Result\Result;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

class ResponseDeserializer
{
    use Traits\ResponseDeserializer\HandlesMutation;

    public function __construct(
        private EventDispatcherInterface $events,
        private array $deserializers,
    ) {}

    public function deserialize(string $json, ResponseModel $responseModel, ?string $toolName = null) : Result {
        $result = match(true) {
            $this->canDeserializeSelf($responseModel) => $this->deserializeSelf(
                $json, $responseModel->instance(), $toolName
            ),
            default => $this->deserializeAny($json, $responseModel)
        };
        $this->events->dispatch(match(true) {
            $result->isFailure() => new ResponseDeserializationFailed(['error' => $result->errorMessage()]),
            default => new ResponseDeserialized(['response' => json_encode($result->unwrap())])
        });
        return $result;
    }

    // INTERNAL ////////////////////////////////////////////////////////

    protected function canDeserializeSelf(ResponseModel $responseModel) : bool {
        return $responseModel->instance() instanceof CanDeserializeSelf;
    }

    protected function deserializeSelf(string $json, CanDeserializeSelf $response, ?string $toolName = null) : Result {
        $this->events->dispatch(new CustomResponseDeserializationAttempt(['response' => $response, 'json' => $json]));
        return Result::try(fn() => $response->fromJson($json, $toolName));
    }

    protected function deserializeAny(string $json, ResponseModel $responseModel) : Result {
        $this->events->dispatch(new ResponseDeserializationAttempt(['responseModel' => $responseModel->toArray(), 'json' => $json]));
        foreach ($this->deserializers as $deserializer) {
            $deserializer = match(true) {
                is_string($deserializer) && is_subclass_of($deserializer, CanDeserializeClass::class) => new $deserializer(),
                $deserializer instanceof CanDeserializeClass => $deserializer,
                default => throw new Exception('Deserializer must implement CanDeserializeClass interface'),
            };

            // we're catching exceptions here - then trying the next deserializer
            // TODO: but the exceptions can be for other reason than deserialization problems

            $result = Result::try(fn() => $deserializer->fromJson($json, $responseModel->returnedClass()));
            if ($result->isSuccess()) {
                return $result;
            }
        }

        // no deserializer - return an anonymous object
        return Result::success($this->toAnonymousObject($json));
        //return Result::failure('No deserializer found for the response');
    }

    private function toAnonymousObject(string $json) : object {
        return json_decode($json);
    }
}
