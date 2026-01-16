<?php declare(strict_types=1);

namespace Cognesy\Instructor\Deserialization;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Events\Response\CustomResponseDeserializationAttempt;
use Cognesy\Instructor\Events\Response\ResponseDeserializationAttempt;
use Cognesy\Instructor\Events\Response\ResponseDeserializationFailed;
use Cognesy\Instructor\Events\Response\ResponseDeserialized;
use Cognesy\Template\Template;
use Cognesy\Utils\Result\Result;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

class ResponseDeserializer implements CanDeserializeResponse
{
    public function __construct(
        private EventDispatcherInterface $events,
        private array $deserializers,
        private StructuredOutputConfig $config,
    ) {}

    #[\Override]
    public function deserialize(array $data, ResponseModel $responseModel) : Result {
        // If OutputFormat is array, return data directly (skip deserialization)
        if ($responseModel->shouldReturnArray()) {
            $response = $this->config->defaultToStdClass()
                ? $this->toAnonymousObject($data)
                : $data;
            $this->events->dispatch(new ResponseDeserialized(['response' => json_encode($response)]));
            return Result::success($response);
        }

        // Determine target class from OutputFormat or fall back to schema class
        $outputFormat = $responseModel->outputFormat();
        $targetClass = $outputFormat?->targetClass() ?? $responseModel->returnedClass();

        // Self-deserializing object with fromArray support
        if ($outputFormat?->isObject()) {
            $instance = $outputFormat->targetInstance();
            if ($instance !== null && method_exists($instance, 'fromArray')) {
                $this->events->dispatch(new CustomResponseDeserializationAttempt([
                    'response' => $instance,
                    'json' => json_encode($data),
                ]));
                /** @var Result<mixed, string> */
                return Result::try(fn() => $instance->fromArray($data));
            }
        }

        // CanDeserializeSelf (fromArray)
        if ($this->canDeserializeSelf($responseModel)) {
            return $this->deserializeSelf($data, $responseModel->instance(), $responseModel->toolName());
        }

        // Use deserializers
        /** @var class-string $targetClass */
        return $this->deserializeAny($data, $targetClass, $responseModel);
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

    protected function deserializeSelf(array $data, CanDeserializeSelf $response, ?string $toolName = null) : Result {
        $this->events->dispatch(new CustomResponseDeserializationAttempt(['response' => $response, 'json' => json_encode($data)]));
        return Result::try(fn() => $response->fromArray($data, $toolName));
    }

    /**
     * @param array<string, mixed> $data
     * @param class-string $targetClass
     */
    protected function deserializeAny(array $data, string $targetClass, ResponseModel $responseModel): Result {
        $this->events->dispatch(new ResponseDeserializationAttempt([
            'responseModel' => $responseModel->toArray(),
            'json' => json_encode($data),
        ]));

        $result = Result::failure('No deserializer available');

        foreach ($this->deserializers as $deserializer) {
            $deserializer = match (true) {
                is_string($deserializer) && is_subclass_of($deserializer, CanDeserializeClass::class) => new $deserializer(),
                $deserializer instanceof CanDeserializeClass => $deserializer,
                default => throw new Exception('Deserializer must implement CanDeserializeClass interface'),
            };

            // Try fromArray
            $result = Result::try(fn() => $deserializer->fromArray($data, $targetClass));
            if ($result->isSuccess()) {
                $this->events->dispatch(new ResponseDeserialized(['response' => json_encode($result->unwrap())]));
                return $result;
            }
        }

        // No deserializer succeeded
        $this->events->dispatch(new ResponseDeserializationFailed(['error' => $result->errorMessage()]));
        return match (true) {
            $this->config->defaultToStdClass() => Result::success($this->toAnonymousObject($data)),
            default => Result::failure($this->makeFailureMessage($this->config->deserializationErrorPrompt(), [
                'json' => json_encode($data),
                'error' => $result->errorMessage(),
                'jsonSchema' => json_encode($responseModel->toJsonSchema()),
            ])),
        };
    }

    private function toAnonymousObject(array $data) : object {
        return (object) $data;
    }

    private function makeFailureMessage(string $template, array $context) : string {
        return Template::arrowpipe()
            ->withTemplateContent($template)
            ->withValues($context)
            ->toText();
    }
}
