<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Logging\EventLog;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Core\StructuredPromptRequestMaterializer;
use Cognesy\Instructor\Creation\ExecutionDriverFactory;
use Cognesy\Instructor\Creation\StructuredOutputExecutionBuilder;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Telemetry\StructuredOutputEventProjector;
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
        private readonly CanMaterializeRequest $requestMaterializer = new StructuredPromptRequestMaterializer(),
    ) {}

    public static function fromConfig(
        LLMConfig $config,
        ?CanHandleEvents $events = null,
        ?CanSendHttpRequests $httpClient = null,
        ?StructuredOutputConfig $structuredConfig = null,
    ): StructuredOutputRuntime {
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
    ): StructuredOutputRuntime {
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
    ): StructuredOutputRuntime {
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
        return EventLog::root('instructor.structured-output.runtime');
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

        // Projector built here, per request, NOT in the constructor. This runtime is
        // long-lived and `onEvent()`/`wiretap()` register against it *after* construction
        // (see :132), so constructor-resolved gates would silently drop this event for every
        // caller who does exactly what the API invites. One `hasListenersFor()` per request
        // is free next to building the envelope.
        (new StructuredOutputEventProjector($this->events))->requestReceived($execution);

        return new PendingStructuredOutput(
            execution: $execution,
            executionDriverFactory: ExecutionDriverFactory::fromParts(
                events: $this->events,
                config: $this->config,
                inference: $this->inference,
                requestMaterializer: $this->requestMaterializer,
                validator: $this->validator,
                transformer: $this->transformer,
                deserializer: $this->deserializer,
                extractor: $this->extractor,
            ),
            events: $this->events,
        );
    }

    public function events(): CanHandleEvents {
        return $this->events;
    }

    /** @param callable(object):void $listener */
    public function onEvent(string $class, callable $listener, int $priority = 0): StructuredOutputRuntime {
        $this->events->addListener($class, $listener, $priority);
        return $this;
    }

    /** @param callable(object):void $listener */
    public function wiretap(callable $listener): StructuredOutputRuntime {
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

    public function withConfig(StructuredOutputConfig $config): StructuredOutputRuntime {
        return $this->with(config: $config);
    }

    /** @deprecated 2.5 Use per-request intoStdClass(); remove in 3.0. */
    public function withDefaultToStdClass(bool $defaultToStdClass = true): StructuredOutputRuntime {
        return $this->withConfig($this->config->with(defaultToStdClass: $defaultToStdClass));
    }

    public function withOutputMode(\Cognesy\Instructor\Enums\OutputMode $outputMode): StructuredOutputRuntime {
        return $this->withConfig($this->config->withOutputMode($outputMode));
    }

    public function withMaxRetries(int $maxRetries): StructuredOutputRuntime {
        return $this->withConfig($this->config->withMaxRetries($maxRetries));
    }

    public function withValidator(CanValidateObject $validator): StructuredOutputRuntime {
        return $this->with(validator: $validator);
    }

    public function withTransformer(CanTransformData $transformer): StructuredOutputRuntime {
        return $this->with(transformer: $transformer);
    }

    public function withDeserializer(CanDeserializeClass $deserializer): StructuredOutputRuntime {
        return $this->with(deserializer: $deserializer);
    }

    public function withExtractor(CanExtractResponse $extractor): StructuredOutputRuntime {
        return $this->with(extractor: $extractor);
    }

    public function withRequestMaterializer(CanMaterializeRequest $requestMaterializer): StructuredOutputRuntime {
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
}
