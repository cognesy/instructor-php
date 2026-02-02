<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\Creation\StructuredOutputExecutionBuilder;
use Cognesy\Instructor\Creation\StructuredOutputPipelineFactory;
use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\Request\SequenceUpdated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputRequestReceived;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Transformation\Contracts\CanTransformData;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

/**
 * The StructuredOutput is facade for handling structured output requests and responses.
 *
 * @template TResponse
 */
class StructuredOutput
{
    use HandlesEvents;

    // From traits - builder instances
    private ?LLMProvider $llmProvider = null;
    protected StructuredOutputExecutionBuilder $executionBuilder;
    private StructuredOutputRequest $request;
    private StructuredOutputConfigBuilder $configBuilder;

    // From traits - callback handlers
    /** @var callable(object): void|null */
    protected $onPartialResponse = null;
    /** @var callable(object): void|null */
    protected $onSequenceUpdate = null;

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

    // CONSTRUCTORS ///////////////////////////////////////////////////////////

    public function __construct(
        ?CanHandleEvents  $events = null,
        ?CanProvideConfig $configProvider = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->configBuilder = new StructuredOutputConfigBuilder(configProvider: $configProvider);
        $this->request = new StructuredOutputRequest();
        $this->executionBuilder = new StructuredOutputExecutionBuilder($this->events);
        $this->llmProvider = LLMProvider::new(
            events: $this->events,
            configProvider: $configProvider,
        );
    }

    // LLM PROVIDER METHODS (from HandlesLLMProvider) /////////////////////////

    public function withDsn(string $dsn): static {
        $this->llmProvider->withDsn($dsn);
        return $this;
    }

    public function using(string $preset): static {
        $this->llmProvider->withLLMPreset($preset);
        return $this;
    }

    public function withLLMProvider(LLMProvider $llm): static {
        $this->llmProvider = $llm;
        return $this;
    }

    public function withLLMConfig(LLMConfig $config): static {
        $this->llmProvider->withConfig($config);
        return $this;
    }

    public function withLLMConfigOverrides(array $overrides): static {
        $this->llmProvider->withConfigOverrides($overrides);
        return $this;
    }

    public function withDriver(CanHandleInference $driver): static {
        $this->llmProvider->withDriver($driver);
        return $this;
    }

    public function withHttpClient(HttpClient $httpClient): static {
        $this->httpClient = $httpClient;
        return $this;
    }

    public function withHttpClientPreset(string $preset): static {
        $builder = new HttpClientBuilder(events: $this->events);
        $this->httpClient = $builder->withPreset($preset)->create();
        return $this;
    }

    /**
     * Set HTTP debug preset explicitly (clearer than withDebugPreset()).
     */
    public function withHttpDebugPreset(?string $preset): static {
        $this->httpDebugPreset = $preset;
        return $this;
    }

    /**
     * Convenience toggle for HTTP debugging.
     */
    public function withHttpDebug(bool $enabled = true): static {
        $preset = match ($enabled) {
            true => 'on',
            false => 'off',
        };
        return $this->withHttpDebugPreset($preset);
    }

    /**
     * Backward-compatible alias for HTTP debug presets.
     */
    public function withDebugPreset(string $preset): static {
        return $this->withHttpDebugPreset($preset);
    }

    public function withClientInstance(string $driverName, object $clientInstance): static {
        $builder = new HttpClientBuilder(events: $this->events);
        $this->httpClient = $builder->withClientInstance(
            driverName: $driverName,
            clientInstance: $clientInstance,
        )->create();
        return $this;
    }

    // REQUEST BUILDER METHODS (from HandlesRequestBuilder) ///////////////////

    public function withMessages(string|array|Message|Messages $messages): static {
        $this->request = $this->request->withMessages($messages);
        return $this;
    }

    public function withInput(mixed $input): static {
        $messages = Messages::fromInput($input);
        $this->request = $this->request->withMessages($messages);
        return $this;
    }

    public function withResponseModel(string|array|object $responseModel): static {
        $this->request = $this->request->withRequestedSchema($responseModel);
        return $this;
    }

    public function withResponseJsonSchema(array|CanProvideJsonSchema $jsonSchema): static {
        $this->request = $this->request->withRequestedSchema($jsonSchema);
        return $this;
    }

    /**
     * @param class-string<TResponse> $class
     * @return StructuredOutput<TResponse>
     */
    public function withResponseClass(string $class): StructuredOutput {
        $this->request = $this->request->withRequestedSchema($class);
        return $this;
    }

    /**
     * @param object<TResponse> $responseObject
     * @return StructuredOutput<TResponse>
     */
    public function withResponseObject(object $responseObject): StructuredOutput {
        $this->request = $this->request->withRequestedSchema($responseObject);
        return $this;
    }

    public function withSystem(string $system): static {
        $this->request = $this->request->withSystem($system);
        return $this;
    }

    public function withPrompt(string $prompt): static {
        $this->request = $this->request->withPrompt($prompt);
        return $this;
    }

    public function withExamples(array $examples): static {
        $this->request = $this->request->withExamples($examples);
        return $this;
    }

    public function withModel(string $model): static {
        $this->request = $this->request->withModel($model);
        return $this;
    }

    public function withOptions(array $options): static {
        $this->request = $this->request->withOptions($options);
        return $this;
    }

    public function withOption(string $key, mixed $value): static {
        $this->request = $this->request->withOptions([$key => $value]);
        return $this;
    }

    public function withStreaming(bool $stream = true): static {
        $this->request = $this->request->withStreamed($stream);
        return $this;
    }

    public function withCachedContext(
        string|array $messages = '',
        string $system = '',
        string $prompt = '',
        array $examples = [],
    ): static {
        $this->request = $this->request->withCachedContext(
            new CachedContext($messages, $system, $prompt, $examples)
        );
        return $this;
    }

    // OUTPUT FORMAT METHODS (from HandlesRequestBuilder) /////////////////////

    /**
     * Return extracted data as raw associative array (skip object deserialization).
     */
    public function intoArray(): static {
        $this->request = $this->request->withOutputFormat(OutputFormat::array());
        return $this;
    }

    /**
     * Hydrate extracted data into the specified class.
     *
     * @param class-string $class Target class for deserialization
     */
    public function intoInstanceOf(string $class): static {
        $this->request = $this->request->withOutputFormat(OutputFormat::instanceOf($class));
        return $this;
    }

    /**
     * Use a self-deserializing object for output.
     *
     * @param CanDeserializeSelf $object Object implementing CanDeserializeSelf
     */
    public function intoObject(CanDeserializeSelf $object): static {
        $this->request = $this->request->withOutputFormat(OutputFormat::selfDeserializing($object));
        return $this;
    }

    // CONFIG BUILDER METHODS (from HandlesConfigBuilder) /////////////////////

    public function withMaxRetries(int $maxRetries): self {
        $this->configBuilder->withMaxRetries($maxRetries);
        return $this;
    }

    public function withOutputMode(OutputMode $outputMode): static {
        $this->configBuilder->withOutputMode($outputMode);
        return $this;
    }

    public function withSchemaName(string $schemaName): static {
        $this->configBuilder->withSchemaName($schemaName);
        return $this;
    }

    public function withToolName(string $toolName): static {
        $this->configBuilder->withToolName($toolName);
        return $this;
    }

    public function withToolDescription(string $toolDescription): static {
        $this->configBuilder->withToolDescription($toolDescription);
        return $this;
    }

    public function withRetryPrompt(string $retryPrompt): static {
        $this->configBuilder->withRetryPrompt($retryPrompt);
        return $this;
    }

    public function withConfig(StructuredOutputConfig $config): static {
        $this->configBuilder->withConfig($config);
        return $this;
    }

    public function withConfigPreset(string $preset): static {
        $this->configBuilder->withConfigPreset($preset);
        return $this;
    }

    public function withConfigProvider(CanProvideConfig $configProvider): static {
        $this->configBuilder->withConfigProvider($configProvider);
        return $this;
    }

    public function withObjectReferences(bool $useObjectReferences): static {
        $this->configBuilder->withUseObjectReferences($useObjectReferences);
        return $this;
    }

    public function withDefaultToStdClass(bool $defaultToStdClass = true): self {
        $this->configBuilder->withDefaultToStdClass($defaultToStdClass);
        return $this;
    }

    public function withDeserializationErrorPrompt(string $deserializationErrorPrompt): self {
        $this->configBuilder->withDeserializationErrorPrompt($deserializationErrorPrompt);
        return $this;
    }

    public function withThrowOnTransformationFailure(bool $throwOnTransformationFailure = true): self {
        $this->configBuilder->withThrowOnTransformationFailure($throwOnTransformationFailure);
        return $this;
    }

    public function withResponseCachePolicy(ResponseCachePolicy $responseCachePolicy): self {
        $this->configBuilder->withResponseCachePolicy($responseCachePolicy);
        return $this;
    }

    // PARTIAL/SEQUENCE UPDATE HANDLERS (from traits) /////////////////////////

    /**
     * Listens to partial responses
     *
     * @param callable(object): void $listener
     */
    public function onPartialUpdate(callable $listener): static {
        $this->onPartialResponse = $listener;
        $this->events->addListener(
            PartialResponseGenerated::class,
            $this->handlePartialResponse(...)
        );
        return $this;
    }

    private function handlePartialResponse(PartialResponseGenerated $event): void {
        if (!is_null($this->onPartialResponse)) {
            ($this->onPartialResponse)($event->partialResponse);
        }
    }

    /**
     * Listens to sequence updates
     *
     * @param callable(object): void $listener
     */
    public function onSequenceUpdate(callable $listener): static {
        $this->onSequenceUpdate = $listener;
        $this->events->addListener(
            SequenceUpdated::class,
            $this->handleSequenceUpdate(...)
        );
        return $this;
    }

    private function handleSequenceUpdate(SequenceUpdated $event): void {
        if (!is_null($this->onSequenceUpdate)) {
            ($this->onSequenceUpdate)($event->sequence);
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
        $this->request = $request;
        return $this;
    }

    /**
     * Sets values for the request builder and configures the StructuredOutput instance
     *
     * @param string|array|Message|Messages|null $messages Text or chat sequence to be used for generating the response.
     * @param string|array|object|null $responseModel The class, JSON schema, or object representing the response format.
     * @param string|null $system The system instructions (optional).
     * @param string|null $prompt The prompt to guide the request's response generation (optional).
     * @param array|null $examples Example data to provide additional context for the request (optional).
     * @param string|null $model Specifies the model to be employed - check LLM documentation for more details.
     * @param int|null $maxRetries The maximum number of retries for the request in case of failure.
     * @param array|null $options Additional LLM options - check LLM documentation for more details.
     * @param string|null $toolName The name of the tool to be used in OutputMode::Tools.
     * @param string|null $toolDescription A description of the tool to be used in OutputMode::Tools.
     * @param string|null $retryPrompt The prompt to be used during retries.
     * @param OutputMode|null $mode The mode of operation for the request.
     *
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
        $this->request = $this->request->with(
            messages: $messages,
            requestedSchema: $responseModel,
            system: $system,
            prompt: $prompt,
            examples: $examples,
            model: $model,
            options: $options,
        );
        $this->configBuilder->with(
            outputMode: $mode,
            maxRetries: $maxRetries,
            retryPrompt: $retryPrompt,
            toolName: $toolName,
            toolDescription: $toolDescription,
            responseCachePolicy: $responseCachePolicy,
        );
        return $this;
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
    public function create(): PendingStructuredOutput {
        if (!$this->request->hasRequestedSchema()) {
            throw new \Exception('Response model cannot be empty. Provide a class name, instance, or schema array.');
        }
        $config = $this->configBuilder->create();
        $request = $this->request;
        $execution = $this->executionBuilder->createWith(
            request: $request,
            config: $config,
        );

        $this->events->dispatch(new StructuredOutputRequestReceived(['request' => $request->toArray()]));

        $pipelineFactory = new StructuredOutputPipelineFactory(
            events: $this->events,
            config: $config,
            llmProvider: $this->llmProvider ?? LLMProvider::new(events: $this->events),
            httpClient: $this->httpClient,
            httpDebugPreset: $this->httpDebugPreset,
            validators: $this->validators,
            transformers: $this->transformers,
            deserializers: $this->deserializers,
            extractor: $this->extractor,
            extractors: $this->extractors,
        );

        $executorFactory = $pipelineFactory->createIteratorFactory();

        $pending = new PendingStructuredOutput(
            execution: $execution,
            executorFactory: $executorFactory,
            events: $this->events,
        );
        return $pending;
    }

    // OVERRIDES ///////////////////////////////////////////////////////////////

    public function withValidators(CanValidateObject|string ...$validators): static {
        $this->validators = $validators;
        return $this;
    }

    public function withTransformers(CanTransformData|string ...$transformers): static {
        $this->transformers = $transformers;
        return $this;
    }

    public function withDeserializers(CanDeserializeClass|string ...$deserializers): static {
        $this->deserializers = $deserializers;
        return $this;
    }

    /**
     * Use a custom response extractor.
     *
     * The extractor transforms raw LLM responses into canonical arrays.
     * Use this to implement custom extraction logic for special formats.
     */
    public function withExtractor(CanExtractResponse $extractor): static {
        // Apply event handler if the extractor supports it (like ResponseExtractor)
        if (method_exists($extractor, 'withEvents')) {
            $this->extractor = $extractor->withEvents($this->events);
        } else {
            $this->extractor = $extractor;
        }
        return $this;
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
        $this->extractors = $extractors;
        return $this;
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
        $this->withStreaming();
        return $this->create()->stream();
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
