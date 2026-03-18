<?php declare(strict_types=1);

namespace Cognesy\Instructor\Deserialization;

use Cognesy\Dynamic\Structure as DynamicStructure;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Enums\ReturnTarget;
use Cognesy\Instructor\Events\Response\CustomResponseDeserializationAttempt;
use Cognesy\Instructor\Events\Response\ResponseDeserializationAttempt;
use Cognesy\Instructor\Events\Response\ResponseDeserializationFailed;
use Cognesy\Instructor\Events\Response\ResponseDeserialized;
use Cognesy\Utils\Result\Result;
use Cognesy\Xprompt\Prompt;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

class ResponseDeserializer implements CanDeserializeResponse
{
    public function __construct(
        private EventDispatcherInterface $events,
        private CanDeserializeClass $deserializer,
        private StructuredOutputConfig $config,
    ) {}

    #[\Override]
    public function deserialize(array $data, ResponseModel $responseModel) : Result {
        $returnTarget = $responseModel->returnTarget();
        if ($returnTarget === ReturnTarget::Array) {
            $this->events->dispatch(new ResponseDeserialized($this->dataSummary($data)));
            return Result::success($data);
        }
        if ($returnTarget === ReturnTarget::UntypedObject) {
            $response = $this->toAnonymousObject($data);
            $this->events->dispatch(new ResponseDeserialized($this->objectSummary($response)));
            return Result::success($response);
        }

        $outputFormat = $responseModel->outputFormat();
        $targetClass = $outputFormat?->targetClass() ?? $responseModel->returnedClass();
        $instance = $responseModel->instance();

        if ($instance instanceof DynamicStructure) {
            return Result::try(fn() => $instance->fromArray($data));
        }

        if ($returnTarget === ReturnTarget::SelfDeserializingObject) {
            $instance = $outputFormat->targetInstance();
            if ($instance !== null && method_exists($instance, 'fromArray')) {
                $this->events->dispatch(new CustomResponseDeserializationAttempt([
                    'class' => $instance::class,
                    'dataKeys' => array_keys($data),
                    'dataKeyCount' => count($data),
                ]));
                /** @var Result<mixed, string> */
                return Result::try(fn() => $instance->fromArray($data));
            }
        }

        if ($this->canDeserializeSelf($responseModel)) {
            return $this->deserializeSelf($data, $responseModel->instance());
        }

        /** @var class-string $targetClass */
        return $this->deserializeAny($data, $targetClass, $responseModel, $returnTarget);
    }

    // INTERNAL ////////////////////////////////////////////////////////

    protected function canDeserializeSelf(ResponseModel $responseModel) : bool {
        return $responseModel->instance() instanceof CanDeserializeSelf;
    }

    protected function deserializeSelf(array $data, CanDeserializeSelf $response) : Result {
        $this->events->dispatch(new CustomResponseDeserializationAttempt([
            'class' => $response::class,
            'dataKeys' => array_keys($data),
            'dataKeyCount' => count($data),
        ]));
        return Result::try(fn() => $response->fromArray($data));
    }

    /**
     * @param array<string, mixed> $data
     * @param class-string $targetClass
     */
    protected function deserializeAny(
        array $data,
        string $targetClass,
        ResponseModel $responseModel,
        ReturnTarget $returnTarget,
    ): Result {
        $this->events->dispatch(new ResponseDeserializationAttempt([
            'targetClass' => $targetClass,
            'returnTarget' => $returnTarget->name,
            'dataKeys' => array_keys($data),
            'dataKeyCount' => count($data),
        ]));

        $result = Result::try(fn() => $this->deserializer->fromArray($data, $targetClass));
        if ($result->isSuccess()) {
            $this->events->dispatch(new ResponseDeserialized($this->objectSummary($result->unwrap())));
            return $result;
        }

        $this->events->dispatch(new ResponseDeserializationFailed(['error' => $result->errorMessage()]));
        return match (true) {
            $returnTarget === ReturnTarget::UntypedObject => Result::success($this->toAnonymousObject($data)),
            default => Result::failure($this->makeFailureMessage($data, $result->errorMessage(), $responseModel)),
        };
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

    private function objectSummary(mixed $value): array {
        if (is_object($value)) {
            $vars = get_object_vars($value);
            return [
                'type' => $value::class,
                'fieldCount' => count($vars),
                'fields' => array_slice(array_keys($vars), 0, 20),
            ];
        }
        return [
            'type' => gettype($value),
        ];
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
