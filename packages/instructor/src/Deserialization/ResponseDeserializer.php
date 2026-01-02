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
    public function deserialize(string $text, ResponseModel $responseModel) : Result {
        $result = match(true) {
            $this->canDeserializeSelf($responseModel) => $this->deserializeSelf(
                $text, $responseModel->instance(), $responseModel->toolName()
            ),
            default => $this->deserializeAny($text, $responseModel)
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

    /**
     * Deserialize from a canonical array (array-first pipeline).
     *
     * @param array<string, mixed> $data Extracted data as associative array
     * @param ResponseModel $responseModel Response model with optional OutputFormat
     * @return Result<mixed, string> Success with deserialized value or Failure
     */
    public function deserializeFromArray(array $data, ResponseModel $responseModel): Result {
        // If OutputFormat is array, return data directly (skip deserialization)
        if ($responseModel->shouldReturnArray()) {
            $this->events->dispatch(new ResponseDeserialized(['response' => json_encode($data)]));
            return Result::success($data);
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

        // Use deserializers with fromArray if available
        /** @var class-string $targetClass */
        return $this->deserializeAnyFromArray($data, $targetClass, $responseModel);
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
        $result = Result::failure('No deserializer available');
        foreach ($this->deserializers as $deserializer) {
            $deserializer = match(true) {
                is_string($deserializer) && is_subclass_of($deserializer, CanDeserializeClass::class) => new $deserializer(),
                $deserializer instanceof CanDeserializeClass => $deserializer,
                default => throw new Exception('Deserializer must implement CanDeserializeClass interface'),
            };

            // we're catching exceptions here - then trying the next deserializer
            // TODO: but the exceptions can be for other reason than deserialization problems

            /** @var class-string $returnedClass */
            $returnedClass = $responseModel->returnedClass();
            $result = Result::try(fn() => $deserializer->fromJson($json, $returnedClass));
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

    /**
     * @param array<string, mixed> $data
     * @param class-string $targetClass
     */
    protected function deserializeAnyFromArray(array $data, string $targetClass, ResponseModel $responseModel): Result {
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

            // Try fromArray if available (preferred for array-first pipeline)
            if (method_exists($deserializer, 'fromArray')) {
                $result = Result::try(fn() => $deserializer->fromArray($data, $targetClass));
                if ($result->isSuccess()) {
                    $this->events->dispatch(new ResponseDeserialized(['response' => json_encode($result->unwrap())]));
                    return $result;
                }
            }
        }

        // Fallback: convert array to JSON and use fromJson
        $json = json_encode($data);
        if ($json === false) {
            $this->events->dispatch(new ResponseDeserializationFailed(['error' => 'Failed to encode array to JSON']));
            return Result::failure('Failed to encode array to JSON');
        }

        foreach ($this->deserializers as $deserializer) {
            $deserializer = match (true) {
                is_string($deserializer) && is_subclass_of($deserializer, CanDeserializeClass::class) => new $deserializer(),
                $deserializer instanceof CanDeserializeClass => $deserializer,
                default => throw new Exception('Deserializer must implement CanDeserializeClass interface'),
            };

            $result = Result::try(fn() => $deserializer->fromJson($json, $targetClass));
            if ($result->isSuccess()) {
                $this->events->dispatch(new ResponseDeserialized(['response' => json_encode($result->unwrap())]));
                return $result;
            }
        }

        // No deserializer succeeded
        $this->events->dispatch(new ResponseDeserializationFailed(['error' => $result->errorMessage()]));
        return match (true) {
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
