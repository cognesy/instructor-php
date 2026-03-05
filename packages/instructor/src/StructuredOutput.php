<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Events\Event;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Transformation\Contracts\CanTransformData;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

/**
 * @template TResponse
 */
final class StructuredOutput implements CanCreateStructuredOutput
{
    private StructuredOutputRequest $request;
    private CanCreateStructuredOutput $runtime;

    public function __construct(
        ?CanCreateStructuredOutput $runtime = null,
        ?CanHandleEvents $events = null,
    ) {
        if ($runtime === null) {
            $this->runtime = StructuredOutputRuntime::fromDefaults(
                events: $events,
            );
        } else {
            $this->runtime = $runtime;
        }
        $this->request = new StructuredOutputRequest();
    }

    public function withRuntime(CanCreateStructuredOutput $runtime): self {
        $copy = clone $this;
        $copy->runtime = $runtime;
        return $copy;
    }

    public static function fromLLMConfig(LLMConfig $config): self {
        return new self(StructuredOutputRuntime::fromConfig($config));
    }

    public static function using(string $preset, ?string $basePath = null): self {
        return self::fromLLMConfig(LLMConfig::fromPreset($preset, $basePath));
    }

    public function runtime(): CanCreateStructuredOutput {
        return $this->runtime;
    }

    public function withConfig(StructuredOutputConfig $config): self {
        $runtime = $this->runtimeOrFail()->withConfig($config);
        return $this->withRuntime($runtime);
    }

    public function withDefaultToStdClass(bool $defaultToStdClass = true): self {
        $runtime = $this->runtimeOrFail();
        $updatedConfig = $runtime->config()->with(defaultToStdClass: $defaultToStdClass);
        $updatedRuntime = $runtime->withConfig($updatedConfig);
        return $this->withRuntime($updatedRuntime);
    }

    public function withOutputMode(OutputMode $outputMode): self {
        $runtime = $this->runtimeOrFail();
        $updated = $runtime->withConfig($runtime->config()->withOutputMode($outputMode));
        return $this->withRuntime($updated);
    }

    public function withMaxRetries(int $maxRetries): self {
        $runtime = $this->runtimeOrFail();
        $updated = $runtime->withConfig($runtime->config()->withMaxRetries($maxRetries));
        return $this->withRuntime($updated);
    }

    /** @param CanExtractResponse|class-string<CanExtractResponse> ...$extractors */
    public function withExtractors(CanExtractResponse|string ...$extractors): self {
        $runtime = $this->runtimeOrFail()->withExtractors($extractors);
        return $this->withRuntime($runtime);
    }

    public function withValidators(CanValidateObject|string ...$validators): self {
        $validatorList = [];
        foreach (array_values($validators) as $validator) {
            if (!is_string($validator)) {
                $validatorList[] = $validator;
                continue;
            }

            if (!class_exists($validator) || !is_subclass_of($validator, CanValidateObject::class)) {
                throw new \InvalidArgumentException("Validator class must implement " . CanValidateObject::class . ": {$validator}");
            }

            /** @var class-string<CanValidateObject> $validator */
            $validatorList[] = $validator;
        }

        $runtime = $this->runtimeOrFail()->withValidators($validatorList);
        return $this->withRuntime($runtime);
    }

    public function withTransformers(CanTransformData|string ...$transformers): self {
        $transformerList = [];
        foreach (array_values($transformers) as $transformer) {
            if (!is_string($transformer)) {
                $transformerList[] = $transformer;
                continue;
            }

            if (!class_exists($transformer) || !is_subclass_of($transformer, CanTransformData::class)) {
                throw new \InvalidArgumentException("Transformer class must implement " . CanTransformData::class . ": {$transformer}");
            }

            /** @var class-string<CanTransformData> $transformer */
            $transformerList[] = $transformer;
        }

        $runtime = $this->runtimeOrFail()->withTransformers($transformerList);
        return $this->withRuntime($runtime);
    }

    public function withDeserializers(CanDeserializeClass|string ...$deserializers): self {
        $deserializerList = [];
        foreach (array_values($deserializers) as $deserializer) {
            if (!is_string($deserializer)) {
                $deserializerList[] = $deserializer;
                continue;
            }

            if (!class_exists($deserializer) || !is_subclass_of($deserializer, CanDeserializeClass::class)) {
                throw new \InvalidArgumentException("Deserializer class must implement " . CanDeserializeClass::class . ": {$deserializer}");
            }

            /** @var class-string<CanDeserializeClass> $deserializer */
            $deserializerList[] = $deserializer;
        }

        $runtime = $this->runtimeOrFail()->withDeserializers($deserializerList);
        return $this->withRuntime($runtime);
    }

    public function withMessages(string|array|Message|Messages $messages): self {
        $copy = clone $this;
        $copy->request = $copy->request->withMessages($messages);
        return $copy;
    }

    public function withInput(string|array|object $input): self {
        $copy = clone $this;
        $copy->request = $copy->request->withMessages(Messages::fromInput($input));
        return $copy;
    }

    public function withResponseModel(string|array|object $responseModel): self {
        $copy = clone $this;
        $copy->request = $copy->request->withRequestedSchema($responseModel);
        return $copy;
    }

    public function withResponseJsonSchema(array|CanProvideJsonSchema $jsonSchema): self {
        $copy = clone $this;
        $copy->request = $copy->request->withRequestedSchema($jsonSchema);
        return $copy;
    }

    /**
     * @param class-string<TResponse> $class
     * @return StructuredOutput<TResponse>
     */
    public function withResponseClass(string $class): StructuredOutput {
        $copy = clone $this;
        $copy->request = $copy->request->withRequestedSchema($class);
        return $copy;
    }

    /**
     * @param object<TResponse> $responseObject
     * @return StructuredOutput<TResponse>
     */
    public function withResponseObject(object $responseObject): StructuredOutput {
        $copy = clone $this;
        $copy->request = $copy->request->withRequestedSchema($responseObject);
        return $copy;
    }

    public function withSystem(string $system): self {
        $copy = clone $this;
        $copy->request = $copy->request->withSystem($system);
        return $copy;
    }

    public function withPrompt(string $prompt): self {
        $copy = clone $this;
        $copy->request = $copy->request->withPrompt($prompt);
        return $copy;
    }

    public function withExamples(array $examples): self {
        $copy = clone $this;
        $copy->request = $copy->request->withExamples($examples);
        return $copy;
    }

    public function withModel(string $model): self {
        $copy = clone $this;
        $copy->request = $copy->request->withModel($model);
        return $copy;
    }

    public function withOptions(array $options): self {
        $copy = clone $this;
        $copy->request = $copy->request->withOptions($options);
        return $copy;
    }

    public function withOption(string $key, mixed $value): self {
        $copy = clone $this;
        $copy->request = $copy->request->withOptions([$key => $value]);
        return $copy;
    }

    public function withStreaming(bool $stream = true): self {
        $copy = clone $this;
        $copy->request = $copy->request->withStreamed($stream);
        return $copy;
    }

    public function withCachedContext(
        string|array $messages = '',
        string $system = '',
        string $prompt = '',
        array $examples = [],
    ): self {
        $copy = clone $this;
        $copy->request = $copy->request->withCachedContext(
            new CachedContext($messages, $system, $prompt, $examples)
        );
        return $copy;
    }

    public function intoArray(): self {
        $copy = clone $this;
        $copy->request = $copy->request->withOutputFormat(OutputFormat::array());
        return $copy;
    }

    /** @param class-string $class */
    public function intoInstanceOf(string $class): self {
        $copy = clone $this;
        $copy->request = $copy->request->withOutputFormat(OutputFormat::instanceOf($class));
        return $copy;
    }

    public function intoObject(CanDeserializeSelf $object): self {
        $copy = clone $this;
        $copy->request = $copy->request->withOutputFormat(OutputFormat::selfDeserializing($object));
        return $copy;
    }

    public function withRequest(StructuredOutputRequest $request): self {
        $copy = clone $this;
        $copy->request = $request;
        return $copy;
    }

    /**
     * @phpstan-ignore-next-line
     */
    public function with(
        string|array|Message|Messages|null $messages = null,
        string|array|object|null $responseModel = null,
        ?string $system = null,
        ?string $prompt = null,
        ?array $examples = null,
        ?string $model = null,
        ?int $maxRetries = null,
        ?array $options = null,
        ?OutputMode $mode = null,
    ): self {
        $copy = clone $this;
        $copy->request = $copy->request->with(
            messages: $messages,
            requestedSchema: $responseModel,
            system: $system,
            prompt: $prompt,
            examples: $examples,
            model: $model,
            options: $options,
        );
        if ($maxRetries !== null) {
            $copy = $copy->withMaxRetries($maxRetries);
        }
        if ($mode !== null) {
            $copy = $copy->withOutputMode($mode);
        }
        return $copy;
    }

    #[\Override]
    public function create(?StructuredOutputRequest $request = null): PendingStructuredOutput {
        $request = $request ?? $this->request;
        if (!$request->hasRequestedSchema()) {
            throw new \InvalidArgumentException('Response model cannot be empty. Provide a class name, instance, or schema array.');
        }
        return $this->runtime->create($request);
    }

    /** @param callable(object):void|null $listener */
    public function wiretap(?callable $listener): self {
        if ($listener === null) {
            return $this;
        }
        if (!$this->runtime instanceof StructuredOutputRuntime) {
            throw new \LogicException('wiretap() is only available when using StructuredOutputRuntime.');
        }
        $this->runtime->events()->wiretap($listener);
        return $this;
    }

    /** @param callable(object):void|null $listener */
    public function onEvent(string $class, ?callable $listener): self {
        if ($listener === null) {
            return $this;
        }
        if (!$this->runtime instanceof StructuredOutputRuntime) {
            throw new \LogicException('onEvent() is only available when using StructuredOutputRuntime.');
        }
        $this->runtime->events()->addListener($class, $listener);
        return $this;
    }

    public function dispatch(Event $event): object {
        if (!$this->runtime instanceof StructuredOutputRuntime) {
            throw new \LogicException('dispatch() is only available when using StructuredOutputRuntime.');
        }
        return $this->runtime->events()->dispatch($event);
    }

    public function response(): InferenceResponse {
        return $this->create()->response();
    }

    public function stream(): StructuredOutputStream {
        return $this->withStreaming()->create()->stream();
    }

    public function get(): mixed {
        return $this->create()->get();
    }

    public function getString(): string {
        return $this->create()->getString();
    }

    public function getFloat(): float {
        return $this->create()->getFloat();
    }

    public function getInt(): int {
        return $this->create()->getInt();
    }

    public function getBoolean(): bool {
        return $this->create()->getBoolean();
    }

    public function getObject(): object {
        return $this->create()->getObject();
    }

    public function getArray(): array {
        return $this->create()->getArray();
    }

    private function runtimeOrFail(): StructuredOutputRuntime {
        if ($this->runtime instanceof StructuredOutputRuntime) {
            return $this->runtime;
        }
        throw new \LogicException('This operation requires StructuredOutputRuntime. Build runtime explicitly and pass it to the facade.');
    }
}
