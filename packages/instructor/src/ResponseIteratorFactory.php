<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Closure;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Contracts\CanDetermineRetry;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanHandleStructuredOutputAttempts;
use Cognesy\Instructor\Contracts\CanStreamStructuredOutputUpdates;
use Cognesy\Instructor\Core\AttemptIterator;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Contracts\ExtractionStrategy;
use Cognesy\Instructor\ResponseIterators\DecoratedPipeline\PartialStreamFactory;
use Cognesy\Instructor\ResponseIterators\DecoratedPipeline\PartialUpdateGenerator;
use Cognesy\Instructor\ResponseIterators\GeneratorBased\PartialGen\GeneratePartialsFromJson;
use Cognesy\Instructor\ResponseIterators\GeneratorBased\PartialGen\GeneratePartialsFromToolCalls;
use Cognesy\Instructor\ResponseIterators\GeneratorBased\StreamingUpdatesGenerator;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\ContentBuffer\ContentBuffer;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\ContentBuffer\ExtractingJsonBuffer;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\ContentBuffer\ToolsBuffer;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\ModularStreamFactory;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\ModularUpdateGenerator;
use Cognesy\Instructor\ResponseIterators\Sync\SyncUpdateGenerator;
use Cognesy\Instructor\RetryPolicy\DefaultRetryPolicy;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidatePartialResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidateResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;

class ResponseIteratorFactory
{
    private readonly LLMProvider $llmProvider;
    private readonly CanDeserializeResponse $responseDeserializer;
    private readonly CanValidateResponse $responseValidator;
    private readonly CanValidatePartialResponse $partialResponseValidator;
    private readonly CanTransformResponse $responseTransformer;
    private readonly CanHandleEvents $events;
    private readonly ?HttpClient $httpClient;
    private readonly ?CanExtractResponse $extractor;
    /** @var ExtractionStrategy[] */
    private readonly array $streamingExtractionStrategies;

    /**
     * @param ExtractionStrategy[] $streamingExtractionStrategies Strategies for streaming extraction
     */
    public function __construct(
        LLMProvider $llmProvider,
        CanDeserializeResponse $responseDeserializer,
        CanValidateResponse $responseValidator,
        CanValidatePartialResponse $partialResponseValidator,
        CanTransformResponse $responseTransformer,
        CanHandleEvents $events,
        ?HttpClient $httpClient = null,
        ?CanExtractResponse $extractor = null,
        array $streamingExtractionStrategies = [],
    ) {
        $this->llmProvider = $llmProvider;
        $this->responseDeserializer = $responseDeserializer;
        $this->responseValidator = $responseValidator;
        $this->partialResponseValidator = $partialResponseValidator;
        $this->responseTransformer = $responseTransformer;
        $this->events = $events;
        $this->httpClient = $httpClient;
        $this->extractor = $extractor;
        $this->streamingExtractionStrategies = $streamingExtractionStrategies;
    }

    public function makeExecutor(StructuredOutputExecution $execution) : CanHandleStructuredOutputAttempts {
        $streamIterator = match(true) {
            $execution->isStreamed() => $this->makeStreamingIterator($execution),
            default => $this->makeSyncIterator(),
        };

        return new AttemptIterator(
            streamIterator: $streamIterator,
            responseGenerator: $this->makeResponseGenerator(),
            retryPolicy: $this->makeRetryPolicy(),
        );
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

    private function makeSyncIterator(): CanStreamStructuredOutputUpdates {
        return new SyncUpdateGenerator(
            inferenceProvider: $this->makeInferenceProvider(),
        );
    }

    private function makeStreamingIterator(StructuredOutputExecution $execution): CanStreamStructuredOutputUpdates {
        $pipeline = $execution->config()->responseIterator;

        return match($pipeline) {
            'modular' => $this->makeModularStreamingIterator(),
            'partials' => $this->makePartialStreamingIterator(),
            'legacy' => $this->makeLegacyStreamingIterator($execution),
            default => $this->makeModularStreamingIterator(),
        };
    }

    private function makeModularStreamingIterator(): CanStreamStructuredOutputUpdates {
        $cleanFactory = new ModularStreamFactory(
            deserializer: $this->responseDeserializer,
            validator: $this->partialResponseValidator,
            transformer: $this->responseTransformer,
            events: $this->events,
            bufferFactory: $this->makeBufferFactory(),
        );

        return new ModularUpdateGenerator(
            inferenceProvider: $this->makeInferenceProvider(),
            factory: $cleanFactory,
        );
    }

    /**
     * Create buffer factory for streaming extraction.
     *
     * @return Closure(OutputMode): ContentBuffer|null Factory that creates ContentBuffer based on OutputMode
     */
    private function makeBufferFactory(): ?Closure
    {
        // No custom strategies = use default buffers
        if (empty($this->streamingExtractionStrategies)) {
            return null;
        }

        $strategies = $this->streamingExtractionStrategies;

        return fn(OutputMode $mode) => match ($mode) {
            OutputMode::Tools => ToolsBuffer::empty(),
            default => ExtractingJsonBuffer::empty($strategies),
        };
    }

    private function makePartialStreamingIterator(): CanStreamStructuredOutputUpdates {
        $partialsFactory = new PartialStreamFactory(
            deserializer: $this->responseDeserializer,
            validator: $this->partialResponseValidator,
            transformer: $this->responseTransformer,
            events: $this->events,
        );

        return new PartialUpdateGenerator(
            inferenceProvider: $this->makeInferenceProvider(),
            partials: $partialsFactory,
        );
    }

    private function makeLegacyStreamingIterator(StructuredOutputExecution $execution): CanStreamStructuredOutputUpdates {
        $partialsGenerator = match($execution->outputMode()) {
            OutputMode::Tools => new GeneratePartialsFromToolCalls(
                $this->responseDeserializer,
                $this->partialResponseValidator,
                $this->responseTransformer,
                $this->events,
            ),
            default => new GeneratePartialsFromJson(
                $this->responseDeserializer,
                $this->partialResponseValidator,
                $this->responseTransformer,
                $this->events,
            ),
        };

        return new StreamingUpdatesGenerator(
            inferenceProvider: $this->makeInferenceProvider(),
            partialsGenerator: $partialsGenerator,
        );
    }

    private function makeInferenceProvider(): InferenceProvider {
        return new InferenceProvider(
            llmProvider: $this->llmProvider,
            requestMaterializer: new RequestMaterializer(),
            events: $this->events,
            httpClient: $this->httpClient,
        );
    }

    private function makeRetryPolicy(): CanDetermineRetry {
        return new DefaultRetryPolicy(
            events: $this->events,
        );
    }

    private function makeResponseGenerator(): CanGenerateResponse {
        return new ResponseGenerator(
            $this->responseDeserializer,
            $this->responseValidator,
            $this->responseTransformer,
            $this->events,
            $this->extractor,
        );
    }
}
