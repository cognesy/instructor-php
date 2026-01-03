<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\Creation\StructuredOutputExecutionBuilder;
use Cognesy\Instructor\Creation\StructuredOutputRequestBuilder;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputRequestReceived;
use Cognesy\Instructor\Extraction\Contracts\CanExtractContent;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\ResponseExtractor;
use Cognesy\Instructor\Transformation\Contracts\CanTransformData;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\PartialValidation;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Instructor\Validation\Validators\SymfonyValidator;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;

/**
 * The StructuredOutput is facade for handling structured output requests and responses.
 *
 * @template TResponse
 */
class StructuredOutput
{
    use HandlesEvents;

    use Traits\HandlesLLMProvider;
    use Traits\HandlesExecutionBuilder;
    /** @use Traits\HandlesRequestBuilder<TResponse> */
    use Traits\HandlesRequestBuilder;
    use Traits\HandlesConfigBuilder;

    use Traits\HandlesPartialUpdates;
    use Traits\HandlesSequenceUpdates;

    /** @var HttpClient|null Facade-level HTTP client (optional) */
    protected ?HttpClient $httpClient = null;
    /** @var string|null Facade-level HTTP debug preset (optional) */
    protected ?string $httpDebugPreset = null;

    protected array $validators = [];
    protected array $transformers = [];
    protected array $deserializers = [];
    protected ?CanExtractResponse $extractor = null;
    /** @var array<CanExtractContent|class-string<CanExtractContent>> */
    protected array $extractors = [];

    // CONSTRUCTORS ///////////////////////////////////////////////////////////

    public function __construct(
        ?CanHandleEvents          $events = null,
        ?CanProvideConfig         $configProvider = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->configBuilder = new StructuredOutputConfigBuilder(configProvider: $configProvider);
        $this->requestBuilder = new StructuredOutputRequestBuilder();
        $this->executionBuilder = new StructuredOutputExecutionBuilder($this->events);
        $this->llmProvider = LLMProvider::new(
            events: $this->events,
            configProvider: $configProvider,
        );
    }

    /**
     * Processes the provided request information and creates a new request to be executed.
     *
     * @param StructuredOutputRequest $request The RequestInfo object containing all necessary data
     * for generating the request.
     */
    public function withRequest(StructuredOutputRequest $request) : static {
        $this->requestBuilder->withRequest($request);
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
    ) : static {
        $this->requestBuilder->with(
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
        );
        return $this;
    }

    /**
     * Creates a new StructuredOutputResponse instance based on the current request builder and configuration.
     *
     * This method initializes the request factory, request handler, and response generator,
     * and returns a StructuredOutputResponse object that can be used to handle the request.
     *
     * @return PendingStructuredOutput<TResponse> A response object providing access to various results retrieval methods.
     */
    public function create() : PendingStructuredOutput {
        $config = $this->configBuilder->create();
        $request = $this->requestBuilder->create();
        $execution = $this->executionBuilder->createWith(
            request: $request,
            config: $config,
        );

        // Apply OutputFormat to ResponseModel
        $outputFormat = $this->getOutputFormat();
        $responseModel = $execution->responseModel();

        // Auto-detect array mode: if no response class is specified, use array output
        if ($outputFormat === null && $responseModel !== null && $responseModel->returnedClass() === '') {
            $outputFormat = OutputFormat::array();
        }

        if ($outputFormat !== null && $responseModel !== null) {
            $execution = $execution->with(
                responseModel: $responseModel->withOutputFormat($outputFormat)
            );
        }

        $this->events->dispatch(new StructuredOutputRequestReceived(['request' => $request->toArray()]));

        $responseDeserializer = new ResponseDeserializer(
            events: $this->events,
            deserializers: $this->deserializers ?: [SymfonyDeserializer::class],
            config: $config,
        );
        $responseValidator = new ResponseValidator(
            events: $this->events,
            validators: $this->validators ?: [SymfonyValidator::class],
            config: $config,
        );
        $responseTransformer = new ResponseTransformer(
            events: $this->events,
            transformers: $this->transformers ?: [],
            config: $config,
        );
        $partialResponseValidator = new PartialValidation(new Config\PartialsGeneratorConfig());

        // Always use extractor for array-first pipeline
        $extractor = match (true) {
            $this->extractor !== null => $this->extractor,
            !empty($this->extractors) => new ResponseExtractor(
                extractors: $this->extractors,
                events: $this->events,
            ),
            default => new ResponseExtractor(events: $this->events),
        };

        // Ensure HttpClient is available; build default if not provided
        if ($this->httpClient !== null) {
            $client = $this->httpClient;
        } else {
            $builder = new HttpClientBuilder(events: $this->events);
            if ($this->httpDebugPreset !== null) {
                $builder = $builder->withDebugPreset($this->httpDebugPreset);
            }
            $client = $builder->create();
        }

        $executorFactory = new ResponseIteratorFactory(
            llmProvider: $this->llmProvider ?? LLMProvider::new(events: $this->events),
            responseDeserializer: $responseDeserializer,
            responseValidator: $responseValidator,
            partialResponseValidator: $partialResponseValidator,
            responseTransformer: $responseTransformer,
            events: $this->events,
            httpClient: $client,
            extractor: $extractor,
        );

        return new PendingStructuredOutput(
            execution: $execution,
            executorFactory: $executorFactory,
            events: $this->events,
        );
    }

    // OVERRIDES ///////////////////////////////////////////////////////////

    public function withValidators(CanValidateObject|string ...$validators) : static {
        $this->validators = $validators;
        return $this;
    }

    public function withTransformers(CanTransformData|string ...$transformers) : static {
        $this->transformers = $transformers;
        return $this;
    }

    public function withDeserializers(CanDeserializeClass|string ...$deserializers) : static {
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
     * @param CanExtractContent|class-string<CanExtractContent> ...$extractors Custom extractors
     */
    public function withExtractors(CanExtractContent|string ...$extractors): static {
        $this->extractors = $extractors;
        return $this;
    }

    // SHORTHANDS //////////////////////////////////////////////////////////

    public function response() : InferenceResponse {
        return $this->create()->response();
    }

    /**
     * Processes a request using provided input, system configurations,
     * and response specifications and returns a streamed result object.
     *
     * @return StructuredOutputStream<TResponse> A streamed version of the response
     */
    public function stream() : StructuredOutputStream {
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
    public function get() : mixed {
        return $this->create()->get();
    }

    public function getString() : string {
        return $this->create()->getString();
    }

    public function getFloat() : float {
        return $this->create()->getFloat();
    }

    public function getInt() : int {
        return $this->create()->getInt();
    }

    public function getBoolean() : bool {
        return $this->create()->getBoolean();
    }

    public function getObject() : object {
        return $this->create()->getObject();
    }

    public function getArray() : array {
        return $this->create()->getArray();
    }
}
