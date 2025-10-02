<?php declare(strict_types=1);

namespace Cognesy\Instructor\Deserialization;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Events\Response\CustomResponseDeserializationAttempt;
use Cognesy\Instructor\Events\Response\ResponseDeserializationAttempt;
use Cognesy\Instructor\Events\Response\ResponseDeserializationFailed;
use Cognesy\Instructor\Events\Response\ResponseDeserialized;
use Cognesy\Template\Template;
use Cognesy\Utils\Result\Result;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

class ResponseDeserializer
{
    public function __construct(
        private EventDispatcherInterface $events,
        private array $deserializers,
        private StructuredOutputConfig $config,
    ) {}

    public function deserialize(string $json, ResponseModel $responseModel, ?string $toolName = null) : Result {
        $result = match(true) {
            $this->canDeserializeSelf($responseModel) => $this->deserializeSelf(
                $json, $responseModel->instance(), $toolName
            ),
            default => $this->deserializeAny($json, $responseModel)
        };
        $this->events->dispatch(match(true) {
            $result->isFailure() => new ResponseDeserializationFailed(['error' => (string) $result->error()]),
            default => new ResponseDeserialized(['response' => json_encode($result->unwrap())])
        });
        return $result;
    }

    /** @param CanDeserializeClass[] $deserializers */
    public function appendDeserializers(array $deserializers) : self {
        $this->deserializers = array_merge($this->deserializers, $deserializers);
        return $this;
    }

    /** @param CanDeserializeClass[] $deserializers */
    public function setDeserializers(array $deserializers) : self {
        $this->deserializers = $deserializers;
        return $this;
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

        // no deserializer - return an anonymous object or fail
        return match(true) {
            $this->config->defaultToStdClass() => Result::success($this->toAnonymousObject($json)),
            default => Result::failure($this->makeFailureMessage($this->config->deserializationErrorPrompt(), [
                'json' => $json,
                'error' => $result->errorMessage(),
                'jsonSchema' => json_encode($responseModel->toJsonSchema()),
            ])),
        };
    }

    private function toAnonymousObject(string $json) : object {
        return json_decode($json);
    }

    private function makeFailureMessage(string $template, array $context) : string {
        return Template::arrowpipe()
            ->withTemplateContent($template)
            ->withValues($context)
            ->toText();
    }
}
