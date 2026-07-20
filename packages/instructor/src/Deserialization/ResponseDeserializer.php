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
use Cognesy\Utils\Result\Result;
use Cognesy\Xprompt\Prompt;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Throwable;

class ResponseDeserializer implements CanDeserializeResponse
{
    public function __construct(
        private EventDispatcherInterface $events,
        private CanDeserializeClass $deserializer,
        private StructuredOutputConfig $config,
    ) {}

    #[\Override]
    public function deserialize(array $data, ResponseModel $responseModel) : Result {
        $outputFormat = $responseModel->outputFormat();
        $this->events->dispatch(new ResponseDeserializationAttempt([
            'outputFormat' => $outputFormat->type->value,
            'targetType' => $outputFormat->targetClass() ?? 'array',
            ...$this->dataSummary($data),
        ]));

        try {
            $result = match (true) {
                $outputFormat->isArray() => Result::success($data),
                $outputFormat->isObject() => $this->deserializeSelfTarget($data, $responseModel),
                $outputFormat->targetClass() === \stdClass::class => Result::success($this->toAnonymousObject($data)),
                $outputFormat->targetClass() === null => Result::failure('Class output target is missing its class name.'),
                default => $this->deserializeAny($data, $outputFormat->targetClass(), $responseModel),
            };
        } catch (Throwable $error) {
            $this->reportFailure($error);
            throw $error;
        }

        if ($result->isSuccess()) {
            $this->events->dispatch(new ResponseDeserialized($this->valueSummary($result->unwrap())));
            return $result;
        }

        $this->reportFailure($result->error());
        return $result;
    }

    // INTERNAL ////////////////////////////////////////////////////////

    protected function deserializeSelf(array $data, CanDeserializeSelf $response) : Result {
        $this->events->dispatch(new CustomResponseDeserializationAttempt([
            'class' => $response::class,
            'dataKeys' => array_keys($data),
            'dataKeyCount' => count($data),
        ]));
        return Result::try(fn() => $response->fromArray($data));
    }

    private function deserializeSelfTarget(array $data, ResponseModel $responseModel): Result
    {
        $instance = $responseModel->outputFormat()->targetInstance();
        return match (true) {
            $instance instanceof CanDeserializeSelf => $this->deserializeSelf($data, $instance),
            default => Result::failure('Self-deserializing output target is missing its instance.'),
        };
    }

    /**
     * @param array<string, mixed> $data
     * @param class-string $targetClass
     */
    protected function deserializeAny(
        array $data,
        string $targetClass,
        ResponseModel $responseModel,
    ): Result {
        $result = Result::try(fn() => $this->deserializer->fromArray($data, $targetClass));
        if ($result->isSuccess()) {
            return $result;
        }

        return Result::failure(new RuntimeException(
            message: $this->makeFailureMessage($data, $result->errorMessage(), $responseModel),
            previous: $result->exception(),
        ));
    }

    private function toAnonymousObject(array $data) : object {
        return (object) $data;
    }

    private function dataSummary(array $data): array {
        return [
            'type' => 'array',
            'keyCount' => count($data),
            'keys' => array_slice(array_keys($data), 0, 20),
        ];
    }

    private function valueSummary(mixed $value): array {
        return match (true) {
            is_object($value) => [
                'type' => $value::class,
                'fieldCount' => count(get_object_vars($value)),
                'fields' => array_slice(array_keys(get_object_vars($value)), 0, 20),
            ],
            is_array($value) => $this->dataSummary($value),
            default => ['type' => get_debug_type($value)],
        };
    }

    private function reportFailure(mixed $error): void
    {
        $this->events->dispatch(new ResponseDeserializationFailed([
            'errorMessage' => 'Response deserialization failed.',
            'errorType' => $error instanceof Throwable ? $error::class : get_debug_type($error),
        ]));
    }

    private function makeFailureMessage(array $data, string $error, ResponseModel $responseModel) : string {
        $promptClass = $this->config->deserializationErrorPromptClass();
        if (!class_exists($promptClass)) {
            throw new InvalidArgumentException("Prompt class does not exist: {$promptClass}");
        }
        if (!is_a($promptClass, Prompt::class, true)) {
            throw new InvalidArgumentException("Prompt class must extend " . Prompt::class . ": {$promptClass}");
        }

        return trim($promptClass::with(
            invalid_payload: $this->encodeJson($data),
            error: $error,
            json_schema: $this->encodeJson($responseModel->toJsonSchema()),
        )->render());
    }

    private function encodeJson(mixed $value): string {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ?: 'null';
    }
}
