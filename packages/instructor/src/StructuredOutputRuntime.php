<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Creation\StructuredOutputExecutionBuilder;
use Cognesy\Instructor\Creation\StructuredOutputPipelineFactory;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputRequestReceived;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Transformation\Contracts\CanTransformData;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

final class StructuredOutputRuntime implements CanCreateStructuredOutput
{
    /** @param array<CanValidateObject|class-string<CanValidateObject>> $validators */
    /** @param array<CanTransformData|class-string<CanTransformData>> $transformers */
    /** @param array<CanDeserializeClass|class-string<CanDeserializeClass>> $deserializers */
    /** @param array<CanExtractResponse|class-string<CanExtractResponse>> $extractors */
    public function __construct(
        private readonly CanCreateInference $inference,
        private readonly CanHandleEvents $events,
        private readonly StructuredOutputConfig $config,
        private readonly array $validators = [],
        private readonly array $transformers = [],
        private readonly array $deserializers = [],
        private readonly array $extractors = [],
    ) {}

    public static function fromConfig(
        LLMConfig $config,
        ?CanHandleEvents $events = null,
        ?CanSendHttpRequests $httpClient = null,
        ?StructuredOutputConfig $structuredConfig = null,
    ): self {
        $events = self::resolveEvents($events);
        return new self(
            inference: InferenceRuntime::fromConfig(
                config: $config,
                events: $events,
                httpClient: $httpClient,
            ),
            events: $events,
            config: self::resolveStructuredConfig($structuredConfig),
        );
    }

    public static function fromDefaults(
        ?CanHandleEvents $events = null,
        ?CanSendHttpRequests $httpClient = null,
        ?StructuredOutputConfig $structuredConfig = null,
        ?LLMConfig $llmConfig = null,
    ): self {
        return self::fromConfig(
            config: $llmConfig ?? LLMProvider::new()->resolveConfig(),
            events: $events,
            httpClient: $httpClient,
            structuredConfig: self::resolveStructuredConfig($structuredConfig),
        );
    }

    public static function fromProvider(
        LLMProvider $provider,
        ?CanHandleEvents $events = null,
        ?CanSendHttpRequests $httpClient = null,
        ?StructuredOutputConfig $structuredConfig = null,
    ): self {
        $events = self::resolveEvents($events);
        return new self(
            inference: InferenceRuntime::fromProvider(
                provider: $provider,
                events: $events,
                httpClient: $httpClient,
            ),
            events: $events,
            config: self::resolveStructuredConfig($structuredConfig),
        );
    }

    private static function resolveEvents(?CanHandleEvents $events): CanHandleEvents {
        if ($events !== null) {
            return $events;
        }
        return new EventDispatcher(name: 'instructor.structured-output.runtime');
    }

    #[\Override]
    public function create(StructuredOutputRequest $request): PendingStructuredOutput {
        if (!$request->hasRequestedSchema()) {
            throw new \InvalidArgumentException('Response model cannot be empty. Provide a class name, instance, or schema array.');
        }

        $execution = (new StructuredOutputExecutionBuilder($this->events))->createWith(
            request: $request,
            config: $this->config,
        );

        $this->events->dispatch(new StructuredOutputRequestReceived(['request' => $request->toArray()]));

        $pipelineFactory = new StructuredOutputPipelineFactory(
            events: $this->events,
            config: $this->config,
            inference: $this->inference,
            validators: $this->validators,
            transformers: $this->transformers,
            deserializers: $this->deserializers,
            extractors: $this->extractors,
        );

        return new PendingStructuredOutput(
            execution: $execution,
            executionDriverFactory: $pipelineFactory->createExecutionDriverFactory(),
            events: $this->events,
        );
    }

    public function events(): CanHandleEvents {
        return $this->events;
    }

    public function onEvent(string $class, callable $listener, int $priority = 0): self {
        $this->events->addListener($class, $listener, $priority);
        return $this;
    }

    public function wiretap(callable $listener): self {
        $this->events->wiretap($listener);
        return $this;
    }

    public function config(): StructuredOutputConfig {
        return $this->config;
    }

    /** @return array<CanValidateObject|class-string<CanValidateObject>> */
    public function validators(): array {
        return $this->validators;
    }

    /** @return array<CanTransformData|class-string<CanTransformData>> */
    public function transformers(): array {
        return $this->transformers;
    }

    /** @return array<CanDeserializeClass|class-string<CanDeserializeClass>> */
    public function deserializers(): array {
        return $this->deserializers;
    }

    /** @return array<CanExtractResponse|class-string<CanExtractResponse>> */
    public function extractors(): array {
        return $this->extractors;
    }

    public function withConfig(StructuredOutputConfig $config): self {
        return $this->with(config: $config);
    }

    public function withDefaultToStdClass(bool $defaultToStdClass = true): self {
        return $this->withConfig($this->config->with(defaultToStdClass: $defaultToStdClass));
    }

    public function withOutputMode(\Cognesy\Instructor\Enums\OutputMode $outputMode): self {
        return $this->withConfig($this->config->withOutputMode($outputMode));
    }

    public function withMaxRetries(int $maxRetries): self {
        return $this->withConfig($this->config->withMaxRetries($maxRetries));
    }

    /** @param array<CanValidateObject|class-string<CanValidateObject>> $validators */
    public function withValidators(array $validators): self {
        return $this->with(validators: $validators);
    }

    /** @param array<CanTransformData|class-string<CanTransformData>> $transformers */
    public function withTransformers(array $transformers): self {
        return $this->with(transformers: $transformers);
    }

    /** @param array<CanDeserializeClass|class-string<CanDeserializeClass>> $deserializers */
    public function withDeserializers(array $deserializers): self {
        return $this->with(deserializers: $deserializers);
    }

    /** @param array<CanExtractResponse|class-string<CanExtractResponse>> $extractors */
    public function withExtractors(array $extractors): self {
        return $this->with(extractors: $extractors);
    }

    private static function resolveStructuredConfig(?StructuredOutputConfig $config): StructuredOutputConfig {
        if ($config !== null) {
            return $config;
        }
        return new StructuredOutputConfig();
    }

    /**
     * @param array<CanValidateObject|class-string<CanValidateObject>>|null $validators
     * @param array<CanTransformData|class-string<CanTransformData>>|null $transformers
     * @param array<CanDeserializeClass|class-string<CanDeserializeClass>>|null $deserializers
     * @param array<CanExtractResponse|class-string<CanExtractResponse>>|null $extractors
     */
    private function with(
        ?StructuredOutputConfig $config = null,
        ?array $validators = null,
        ?array $transformers = null,
        ?array $deserializers = null,
        ?array $extractors = null,
    ): self {
        return new self(
            inference: $this->inference,
            events: $this->events,
            config: $config ?? $this->config,
            validators: $validators ?? $this->validators,
            transformers: $transformers ?? $this->transformers,
            deserializers: $deserializers ?? $this->deserializers,
            extractors: $extractors ?? $this->extractors,
        );
    }
}
