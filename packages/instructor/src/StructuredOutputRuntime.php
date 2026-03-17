<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Core\RequestMaterializer;
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
    public function __construct(
        private readonly CanCreateInference $inference,
        private readonly CanHandleEvents $events,
        private readonly StructuredOutputConfig $config,
        private readonly ?CanValidateObject $validator = null,
        private readonly ?CanTransformData $transformer = null,
        private readonly ?CanDeserializeClass $deserializer = null,
        private readonly ?CanExtractResponse $extractor = null,
        private readonly CanMaterializeRequest $requestMaterializer = new RequestMaterializer(),
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

        $this->events->dispatch(new StructuredOutputRequestReceived($this->requestReceivedPayload($execution)));

        $pipelineFactory = new StructuredOutputPipelineFactory(
            events: $this->events,
            config: $this->config,
            inference: $this->inference,
            requestMaterializer: $this->requestMaterializer,
            validator: $this->validator,
            transformer: $this->transformer,
            deserializer: $this->deserializer,
            extractor: $this->extractor,
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

    /** @param callable(object):void $listener */
    public function onEvent(string $class, callable $listener, int $priority = 0): self {
        $this->events->addListener($class, $listener, $priority);
        return $this;
    }

    /** @param callable(object):void $listener */
    public function wiretap(callable $listener): self {
        $this->events->wiretap($listener);
        return $this;
    }

    public function config(): StructuredOutputConfig {
        return $this->config;
    }

    public function validator(): ?CanValidateObject {
        return $this->validator;
    }

    public function transformer(): ?CanTransformData {
        return $this->transformer;
    }

    public function deserializer(): ?CanDeserializeClass {
        return $this->deserializer;
    }

    public function extractor(): ?CanExtractResponse {
        return $this->extractor;
    }

    public function requestMaterializer(): CanMaterializeRequest {
        return $this->requestMaterializer;
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

    public function withValidator(CanValidateObject $validator): self {
        return $this->with(validator: $validator);
    }

    public function withTransformer(CanTransformData $transformer): self {
        return $this->with(transformer: $transformer);
    }

    public function withDeserializer(CanDeserializeClass $deserializer): self {
        return $this->with(deserializer: $deserializer);
    }

    public function withExtractor(CanExtractResponse $extractor): self {
        return $this->with(extractor: $extractor);
    }

    public function withRequestMaterializer(CanMaterializeRequest $requestMaterializer): self {
        return $this->with(requestMaterializer: $requestMaterializer);
    }

    private static function resolveStructuredConfig(?StructuredOutputConfig $config): StructuredOutputConfig {
        if ($config !== null) {
            return $config;
        }
        return new StructuredOutputConfig();
    }

    private function with(
        ?StructuredOutputConfig $config = null,
        ?CanValidateObject $validator = null,
        ?CanTransformData $transformer = null,
        ?CanDeserializeClass $deserializer = null,
        ?CanExtractResponse $extractor = null,
        ?CanMaterializeRequest $requestMaterializer = null,
    ): self {
        return new self(
            inference: $this->inference,
            events: $this->events,
            config: $config ?? $this->config,
            validator: $validator ?? $this->validator,
            transformer: $transformer ?? $this->transformer,
            deserializer: $deserializer ?? $this->deserializer,
            extractor: $extractor ?? $this->extractor,
            requestMaterializer: $requestMaterializer ?? $this->requestMaterializer,
        );
    }

    private function requestReceivedPayload(\Cognesy\Instructor\Data\StructuredOutputExecution $execution) : array
    {
        $request = $execution->request();
        $requestedSchema = $request->requestedSchema();

        $payload = [
            'requestId' => $request->id()->toString(),
            'executionId' => $execution->id()->toString(),
            'phase' => 'request.received',
            'phaseId' => $this->phaseId($execution->id()->toString(), 'request.received'),
            'model' => $request->model(),
            'messageCount' => count($request->messages()->toArray()),
            'isStreamed' => $request->isStreamed(),
            'requestedSchemaType' => is_array($requestedSchema) ? 'array' : (is_object($requestedSchema) ? 'object' : 'string'),
        ];

        if (is_array($requestedSchema)) {
            $payload['requestedSchemaKeyCount'] = count($requestedSchema);
            return $payload;
        }

        if ($requestedSchema !== '') {
            $payload['requestedSchemaClass'] = is_object($requestedSchema)
                ? $requestedSchema::class
                : ltrim($requestedSchema, '\\');
        }

        return $payload;
    }

    private function phaseId(string $executionId, string $phase, ?string $attemptId = null) : string
    {
        return match ($attemptId) {
            null => "{$executionId}:{$phase}",
            default => "{$executionId}:{$phase}:{$attemptId}",
        };
    }
}
