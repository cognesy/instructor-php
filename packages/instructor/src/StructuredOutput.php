<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\Request\SequenceUpdated;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Transformation\Contracts\CanTransformData;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Contracts\CanAcceptLLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanResolveLLMConfig;
use Cognesy\Polyglot\Inference\Contracts\HasExplicitInferenceDriver;
use Cognesy\Polyglot\Inference\Creation\InferenceDriverFactory;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Inference\Pricing\StaticPricingResolver;
use Cognesy\Polyglot\Inference\Traits\HandlesLLMProvider;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The StructuredOutput is facade for handling structured output requests and responses.
 *
 * @template TResponse
 */
class StructuredOutput implements CanAcceptLLMConfig, CanCreateStructuredOutput
{
    use HandlesEvents;
    use HandlesLLMProvider;

    // Builder instances
    private StructuredOutputRequest $request;
    private StructuredOutputConfigBuilder $configBuilder;

    // Callback handlers
    /** @var array<callable(object): void> */
    protected array $onPartialResponse = [];
    /** @var array<callable(object): void> */
    protected array $onSequenceUpdate = [];

    // Local properties
    /** @var HttpClient|null Facade-level HTTP client (optional) */
    protected ?HttpClient $httpClient = null;
    /** @var string|null Facade-level HTTP debug preset (optional) */
    protected ?string $httpDebugPreset = null;

    protected array $validators = [];
    protected array $transformers = [];
    protected array $deserializers = [];
    protected ?CanExtractResponse $extractor = null;
    /** @var array<CanExtractResponse|class-string<CanExtractResponse>> */
    protected array $extractors = [];
    private ?InferenceDriverFactory $inferenceFactory = null;
    private ?int $inferenceFactoryEventBusId = null;
    private ?StructuredOutputRuntime $runtimeCache = null;
    private bool $runtimeCacheDirty = true;

    // CONSTRUCTORS ///////////////////////////////////////////////////////////

    public function __construct(
        null|CanHandleEvents|EventDispatcherInterface $events = null,
        ?CanProvideConfig $configProvider = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->configBuilder = new StructuredOutputConfigBuilder(configProvider: $configProvider);
        $this->request = new StructuredOutputRequest();
        $this->llmProvider = LLMProvider::new(
            configProvider: $configProvider,
        );
    }

    private function cloneWithConfigBuilder(): static {
        $copy = clone $this;
        $copy->configBuilder = clone $this->configBuilder;
        return $copy;
    }

    public function withEventHandler(CanHandleEvents|EventDispatcherInterface $events): static {
        $copy = clone $this;
        $copy->events = EventBusResolver::using($events);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    // LLM PROVIDER OVERRIDES /////////////////////////////////////////////////

    public function withClientInstance(string $driverName, object $clientInstance): static {
        $copy = clone $this;
        $builder = new HttpClientBuilder(events: $copy->events);
        $copy->httpClient = $builder->withClientInstance(
            driverName: $driverName,
            clientInstance: $clientInstance,
        )->create();
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    // REQUEST BUILDER METHODS (from HandlesRequestBuilder) ///////////////////

    public function withMessages(string|array|Message|Messages $messages): static {
        $copy = clone $this;
        $copy->request = $copy->request->withMessages($messages);
        return $copy;
    }

    public function withInput(mixed $input): static {
        $copy = clone $this;
        $messages = Messages::fromInput($input);
        $copy->request = $copy->request->withMessages($messages);
        return $copy;
    }

    public function withResponseModel(string|array|object $responseModel): static {
        $copy = clone $this;
        $copy->request = $copy->request->withRequestedSchema($responseModel);
        return $copy;
    }

    public function withResponseJsonSchema(array|CanProvideJsonSchema $jsonSchema): static {
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

    public function withSystem(string $system): static {
        $copy = clone $this;
        $copy->request = $copy->request->withSystem($system);
        return $copy;
    }

    public function withPrompt(string $prompt): static {
        $copy = clone $this;
        $copy->request = $copy->request->withPrompt($prompt);
        return $copy;
    }

    public function withExamples(array $examples): static {
        $copy = clone $this;
        $copy->request = $copy->request->withExamples($examples);
        return $copy;
    }

    public function withModel(string $model): static {
        $copy = clone $this;
        $copy->request = $copy->request->withModel($model);
        return $copy;
    }

    public function withOptions(array $options): static {
        $copy = clone $this;
        $copy->request = $copy->request->withOptions($options);
        return $copy;
    }

    public function withOption(string $key, mixed $value): static {
        $copy = clone $this;
        $copy->request = $copy->request->withOptions([$key => $value]);
        return $copy;
    }

    public function withStreaming(bool $stream = true): static {
        $copy = clone $this;
        $copy->request = $copy->request->withStreamed($stream);
        return $copy;
    }

    public function withCachedContext(
        string|array $messages = '',
        string $system = '',
        string $prompt = '',
        array $examples = [],
    ): static {
        $copy = clone $this;
        $copy->request = $copy->request->withCachedContext(
            new CachedContext($messages, $system, $prompt, $examples)
        );
        return $copy;
    }

    // OUTPUT FORMAT METHODS (from HandlesRequestBuilder) /////////////////////

    /**
     * Return extracted data as raw associative array (skip object deserialization).
     */
    public function intoArray(): static {
        $copy = clone $this;
        $copy->request = $copy->request->withOutputFormat(OutputFormat::array());
        return $copy;
    }

    /**
     * Hydrate extracted data into the specified class.
     *
     * @param class-string $class Target class for deserialization
     */
    public function intoInstanceOf(string $class): static {
        $copy = clone $this;
        $copy->request = $copy->request->withOutputFormat(OutputFormat::instanceOf($class));
        return $copy;
    }

    /**
     * Use a self-deserializing object for output.
     *
     * @param CanDeserializeSelf $object Object implementing CanDeserializeSelf
     */
    public function intoObject(CanDeserializeSelf $object): static {
        $copy = clone $this;
        $copy->request = $copy->request->withOutputFormat(OutputFormat::selfDeserializing($object));
        return $copy;
    }

    // CONFIG BUILDER METHODS (from HandlesConfigBuilder) /////////////////////

    public function withMaxRetries(int $maxRetries): static {
        $copy = $this->cloneWithConfigBuilder();
        $copy->configBuilder->withMaxRetries($maxRetries);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withOutputMode(OutputMode $outputMode): static {
        $copy = $this->cloneWithConfigBuilder();
        $copy->configBuilder->withOutputMode($outputMode);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withSchemaName(string $schemaName): static {
        $copy = $this->cloneWithConfigBuilder();
        $copy->configBuilder->withSchemaName($schemaName);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withToolName(string $toolName): static {
        $copy = $this->cloneWithConfigBuilder();
        $copy->configBuilder->withToolName($toolName);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withToolDescription(string $toolDescription): static {
        $copy = $this->cloneWithConfigBuilder();
        $copy->configBuilder->withToolDescription($toolDescription);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withRetryPrompt(string $retryPrompt): static {
        $copy = $this->cloneWithConfigBuilder();
        $copy->configBuilder->withRetryPrompt($retryPrompt);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withConfig(StructuredOutputConfig $config): static {
        $copy = $this->cloneWithConfigBuilder();
        $copy->configBuilder->withConfig($config);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withConfigPreset(string $preset): static {
        $copy = $this->cloneWithConfigBuilder();
        $copy->configBuilder->withConfigPreset($preset);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withConfigProvider(CanProvideConfig $configProvider): static {
        $copy = $this->cloneWithConfigBuilder();
        $copy->configBuilder->withConfigProvider($configProvider);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withObjectReferences(bool $useObjectReferences): static {
        $copy = $this->cloneWithConfigBuilder();
        $copy->configBuilder->withUseObjectReferences($useObjectReferences);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withDefaultToStdClass(bool $defaultToStdClass = true): static {
        $copy = $this->cloneWithConfigBuilder();
        $copy->configBuilder->withDefaultToStdClass($defaultToStdClass);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withDeserializationErrorPrompt(string $deserializationErrorPrompt): static {
        $copy = $this->cloneWithConfigBuilder();
        $copy->configBuilder->withDeserializationErrorPrompt($deserializationErrorPrompt);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withThrowOnTransformationFailure(bool $throwOnTransformationFailure = true): static {
        $copy = $this->cloneWithConfigBuilder();
        $copy->configBuilder->withThrowOnTransformationFailure($throwOnTransformationFailure);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withResponseCachePolicy(ResponseCachePolicy $responseCachePolicy): static {
        $copy = $this->cloneWithConfigBuilder();
        $copy->configBuilder->withResponseCachePolicy($responseCachePolicy);
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    // PARTIAL/SEQUENCE UPDATE HANDLERS (from traits) /////////////////////////

    /**
     * Listens to partial responses
     *
     * @param callable(object): void $listener
     */
    public function onPartialUpdate(callable $listener): static {
        if (empty($this->onPartialResponse)) {
            $this->events->addListener(
                PartialResponseGenerated::class,
                $this->handlePartialResponse(...)
            );
        }
        $this->onPartialResponse[] = $listener;
        return $this;
    }

    private function handlePartialResponse(PartialResponseGenerated $event): void {
        foreach ($this->onPartialResponse as $listener) {
            $listener($event->partialResponse);
        }
    }

    /**
     * Listens to sequence updates
     *
     * @param callable(object): void $listener
     */
    public function onSequenceUpdate(callable $listener): static {
        if (empty($this->onSequenceUpdate)) {
            $this->events->addListener(
                SequenceUpdated::class,
                $this->handleSequenceUpdate(...)
            );
        }
        $this->onSequenceUpdate[] = $listener;
        return $this;
    }

    private function handleSequenceUpdate(SequenceUpdated $event): void {
        foreach ($this->onSequenceUpdate as $listener) {
            $listener($event->sequence);
        }
    }

    // REQUEST HANDLING ///////////////////////////////////////////////////////

    /**
     * Processes the provided request information and creates a new request to be executed.
     *
     * @param StructuredOutputRequest $request The RequestInfo object containing all necessary data
     * for generating the request.
     */
    public function withRequest(StructuredOutputRequest $request): static {
        $copy = clone $this;
        $copy->request = $request;
        return $copy;
    }

    /**
     * Sets values for the request builder and configures the StructuredOutput instance
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
        ?string $toolName = null,
        ?string $toolDescription = null,
        ?string $retryPrompt = null,
        ?OutputMode $mode = null,
        ?ResponseCachePolicy $responseCachePolicy = null,
    ): static {
        $copy = $this->cloneWithConfigBuilder();
        $copy->request = $copy->request->with(
            messages: $messages,
            requestedSchema: $responseModel,
            system: $system,
            prompt: $prompt,
            examples: $examples,
            model: $model,
            options: $options,
        );
        $copy->configBuilder->with(
            outputMode: $mode,
            maxRetries: $maxRetries,
            retryPrompt: $retryPrompt,
            toolName: $toolName,
            toolDescription: $toolDescription,
            responseCachePolicy: $responseCachePolicy,
        );
        if (
            $mode !== null
            || $maxRetries !== null
            || $retryPrompt !== null
            || $toolName !== null
            || $toolDescription !== null
            || $responseCachePolicy !== null
        ) {
            $copy->invalidateRuntimeCache();
        }
        return $copy;
    }

    /**
     * Creates a new StructuredOutputResponse instance based on the current request builder and configuration.
     *
     * This method initializes the request factory, request handler, and response generator,
     * and returns a StructuredOutputResponse object that can be used to handle the request.
     * StructuredOutput instances are single-use; call create() once per instance.
     *
     * @return PendingStructuredOutput<TResponse> A response object providing access to various results retrieval methods.
     */
    public function create(?StructuredOutputRequest $request = null): PendingStructuredOutput {
        $request = $request ?? $this->request;
        if (!$request->hasRequestedSchema()) {
            throw new \InvalidArgumentException('Response model cannot be empty. Provide a class name, instance, or schema array.');
        }
        return $this->toRuntime()->create($request);
    }

    public function toRuntime(): StructuredOutputRuntime {
        if (!$this->runtimeCacheDirty && $this->runtimeCache !== null) {
            return $this->runtimeCache;
        }

        $this->runtimeCache = new StructuredOutputRuntime(
            inference: $this->makeInferenceRuntime(),
            events: $this->events,
            config: $this->configBuilder->create(),
            validators: $this->validators,
            transformers: $this->transformers,
            deserializers: $this->deserializers,
            extractor: $this->extractor,
            extractors: $this->extractors,
        );
        $this->runtimeCacheDirty = false;
        return $this->runtimeCache;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function makeInferenceRuntime() : CanCreateInference {
        $resolver = $this->llmResolver ?? $this->llmProvider;
        $config = $resolver->resolveConfig();
        $driver = $this->makeInferenceDriver(
            httpClient: $this->makeHttpClient(),
            resolver: $resolver,
            config: $config,
        );

        return new InferenceRuntime(
            driver: $driver,
            events: $this->events,
            pricingResolver: new StaticPricingResolver($config->getPricing()),
        );
    }

    private function getInferenceFactory() : InferenceDriverFactory {
        $eventsId = spl_object_id($this->events);
        if ($this->inferenceFactory === null || $this->inferenceFactoryEventBusId !== $eventsId) {
            $this->inferenceFactory = new InferenceDriverFactory($this->events);
            $this->inferenceFactoryEventBusId = $eventsId;
        }
        return $this->inferenceFactory;
    }

    private function makeInferenceDriver(
        HttpClient $httpClient,
        CanResolveLLMConfig $resolver,
        LLMConfig $config,
    ) : CanProcessInferenceRequest {
        $explicit = $resolver instanceof HasExplicitInferenceDriver
            ? $resolver->explicitInferenceDriver()
            : null;

        if ($explicit !== null) {
            return $explicit;
        }

        return $this->getInferenceFactory()->makeDriver(
            config: $config,
            httpClient: $httpClient,
        );
    }

    private function makeHttpClient() : HttpClient {
        if ($this->httpClient !== null) {
            return $this->httpClient;
        }
        $builder = new HttpClientBuilder(events: $this->events);
        if ($this->httpDebugPreset !== null) {
            $builder = $builder->withDebugPreset($this->httpDebugPreset);
        }
        return $builder->create();
    }

    // OVERRIDES ///////////////////////////////////////////////////////////////

    public function withValidators(CanValidateObject|string ...$validators): static {
        $copy = clone $this;
        $copy->validators = $validators;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withTransformers(CanTransformData|string ...$transformers): static {
        $copy = clone $this;
        $copy->transformers = $transformers;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withDeserializers(CanDeserializeClass|string ...$deserializers): static {
        $copy = clone $this;
        $copy->deserializers = $deserializers;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    /**
     * Use a custom response extractor.
     *
     * The extractor transforms raw LLM responses into canonical arrays.
     * Use this to implement custom extraction logic for special formats.
     */
    public function withExtractor(CanExtractResponse $extractor): static {
        $copy = clone $this;
        // Apply event handler if the extractor supports it (like ResponseExtractor)
        if (method_exists($extractor, 'withEvents')) {
            $copy->extractor = $extractor->withEvents($copy->events);
            $copy->invalidateRuntimeCache();
            return $copy;
        }
        $copy->extractor = $extractor;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    /**
     * Configure extractors for response content extraction.
     *
     * Extractors are tried in order until one succeeds. This method creates
     * a ResponseExtractor with the specified extractors for both sync and
     * streaming operations.
     *
     * @param CanExtractResponse|class-string<CanExtractResponse> ...$extractors Custom extractors
     */
    public function withExtractors(CanExtractResponse|string ...$extractors): static {
        $copy = clone $this;
        $copy->extractors = $extractors;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    protected function invalidateRuntimeCache(): void {
        $this->runtimeCache = null;
        $this->runtimeCacheDirty = true;
    }

    // SHORTHANDS //////////////////////////////////////////////////////////////

    public function response(): InferenceResponse {
        return $this->create()->response();
    }

    /**
     * Processes a request using provided input, system configurations,
     * and response specifications and returns a streamed result object.
     *
     * @return StructuredOutputStream<TResponse> A streamed version of the response
     */
    public function stream(): StructuredOutputStream {
        return $this->withStreaming()->create()->stream();
    }

    // get results converted to specific types

    /**
     * Processes a request using provided input, system configurations,
     * and response specifications and returns the result directly.
     *
     * @return TResponse A result of processing the request transformed to the target value
     */
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

}
