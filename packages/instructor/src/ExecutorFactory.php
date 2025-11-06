<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanDetermineRetry;
use Cognesy\Instructor\Contracts\CanExecuteStructuredOutput;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanHandleStructuredOutputAttempts;
use Cognesy\Instructor\Contracts\CanStreamStructuredOutputUpdates;
use Cognesy\Instructor\Core\AttemptIterator;
use Cognesy\Instructor\Core\DefaultRetryPolicy;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Core\RetryHandler;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Executors\Partials\PartialStreamFactory;
use Cognesy\Instructor\Executors\Partials\PartialStreamingRequestHandler;
use Cognesy\Instructor\Executors\Partials\PartialStreamingUpdateGenerator;
use Cognesy\Instructor\Executors\Streaming\PartialGen\GeneratePartialsFromJson;
use Cognesy\Instructor\Executors\Streaming\PartialGen\GeneratePartialsFromToolCalls;
use Cognesy\Instructor\Executors\Streaming\StreamingRequestHandler;
use Cognesy\Instructor\Executors\Streaming\StreamingUpdatesGenerator;
use Cognesy\Instructor\Executors\Sync\SyncRequestHandler;
use Cognesy\Instructor\Executors\Sync\SyncUpdateGenerator;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidatePartialResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidateResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;

class ExecutorFactory
{
    private readonly LLMProvider $llmProvider;
    private readonly CanDeserializeResponse $responseDeserializer;
    private readonly CanValidateResponse $responseValidator;
    private readonly CanValidatePartialResponse $partialResponseValidator;
    private readonly CanTransformResponse $responseTransformer;
    private readonly CanHandleEvents $events;
    private readonly ?HttpClient $httpClient;

    public function __construct(
        LLMProvider $llmProvider,
        CanDeserializeResponse $responseDeserializer,
        CanValidateResponse $responseValidator,
        CanValidatePartialResponse $partialResponseValidator,
        CanTransformResponse $responseTransformer,
        CanHandleEvents $events,
        ?HttpClient $httpClient = null,
    ) {
        $this->llmProvider = $llmProvider;
        $this->responseDeserializer = $responseDeserializer;
        $this->responseValidator = $responseValidator;
        $this->partialResponseValidator = $partialResponseValidator;
        $this->responseTransformer = $responseTransformer;
        $this->events = $events;
        $this->httpClient = $httpClient;
    }

    public function makeExecutor(StructuredOutputExecution $execution) : CanHandleStructuredOutputAttempts {
        // Unified execution model: both streaming and sync use AttemptIterator
        $streamIterator = match(true) {
            $execution->isStreamed() => $this->makeStreamingIterator($execution),
            default => $this->makeSyncIterator(),
        };

        return new AttemptIterator(
            streamIterator: $streamIterator,
            responseGenerator: $this->makeResponseProcessor(),
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
        // Use streamingPipeline config if available, fallback to streamingDriver for backward compatibility
        $pipeline = $execution->config()->streamingPipeline
            ?? $this->mapLegacyDriverToPipeline($execution->config()->streamingDriver);

        return match($pipeline) {
            'partials' => $this->makePartialStreamingIterator(),
            'legacy' => $this->makeLegacyStreamingIterator($execution),
            default => $this->makePartialStreamingIterator(),
        };
    }

    private function mapLegacyDriverToPipeline(string $driver): string {
        return match($driver) {
            'partials', 'partials-iterative' => 'partials',
            'generator', 'generator-iterative' => 'legacy',
            default => 'partials',
        };
    }

    private function makePartialStreamingIterator(): CanStreamStructuredOutputUpdates {
        $partialsFactory = new PartialStreamFactory(
            deserializer: $this->responseDeserializer,
            validator: $this->partialResponseValidator,
            transformer: $this->responseTransformer,
            events: $this->events,
        );

        return new PartialStreamingUpdateGenerator(
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

    // DEPRECATED LEGACY METHODS - DO NOT USE /////////////////////////////////////

    /** @deprecated Use makeSyncIterator instead */
    private function makeSyncHandler(
        InferenceProvider $inferenceProvider,
        CanGenerateResponse $processor,
        RetryHandler $retryHandler,
    ): CanExecuteStructuredOutput {
        return new SyncRequestHandler(
            inferenceProvider: $inferenceProvider,
            processor: $processor,
            retryHandler: $retryHandler,
        );
    }

    /** @deprecated Use makeStreamingIterator instead */
    private function makeStreamingHandler(
        InferenceProvider $inferenceProvider,
        CanGenerateResponse $processor,
        RetryHandler $retryHandler,
        StructuredOutputConfig $config,
    ): CanExecuteStructuredOutput {
        return match($config->streamingDriver) {
            'partials' => $this->makeTransducerHandler(
                inferenceProvider: $inferenceProvider,
                processor: $processor,
                retryHandler: $retryHandler,
            ),
            'generator' => $this->makeGeneratorHandler(
                inferenceProvider: $inferenceProvider,
                processor: $processor,
                retryHandler: $retryHandler,
                config: $config,
            ),
            'partials-iterative' => $this->makeIterativeTransducerHandler(
                inferenceProvider: $inferenceProvider,
                processor: $processor,
                retryPolicy: $this->makeRetryPolicy(),
            ),
            'generator-iterative' => $this->makeIterativeGeneratorHandler(
                inferenceProvider: $inferenceProvider,
                processor: $processor,
                retryPolicy: $this->makeRetryPolicy(),
                config: $config,
            ),
        };
    }

    /** @deprecated Use makeLegacyStreamingIterator instead */
    private function makeGeneratorHandler(
        InferenceProvider $inferenceProvider,
        CanGenerateResponse $processor,
        RetryHandler $retryHandler,
        StructuredOutputConfig $config,
    ) : CanExecuteStructuredOutput {
        $partialsGenerator = match($config->outputMode()) {
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
        return new StreamingRequestHandler(
            inferenceProvider: $inferenceProvider,
            partialsGenerator: $partialsGenerator,
            responseGenerator: $processor,
            retryHandler: $retryHandler,
        );
    }

    /** @deprecated Use makePartialStreamingIterator instead */
    private function makeTransducerHandler(
        InferenceProvider $inferenceProvider,
        CanGenerateResponse $processor,
        RetryHandler $retryHandler,
    ) : CanExecuteStructuredOutput {
        $partialsFactory = new PartialStreamFactory(
            deserializer: $this->responseDeserializer,
            validator: $this->partialResponseValidator,
            transformer: $this->responseTransformer,
            events: $this->events,
        );
        return new PartialStreamingRequestHandler(
            inferenceProvider: $inferenceProvider,
            partials: $partialsFactory,
            responseGenerator: $processor,
            retryHandler: $retryHandler,
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

    private function makeRetryHandler(): RetryHandler {
        return new RetryHandler(
            events: $this->events,
        );
    }

    private function makeRetryPolicy(): CanDetermineRetry {
        return new DefaultRetryPolicy(
            events: $this->events,
        );
    }

    private function makeResponseProcessor(): CanGenerateResponse {
        return new ResponseGenerator(
            $this->responseDeserializer,
            $this->responseValidator,
            $this->responseTransformer,
            $this->events,
        );
    }

    // DEPRECATED: These methods replaced by makeExecutor's unified pattern /////////

    /**
     * @deprecated Now integrated into makeExecutor's unified pattern
     * Create iterative handler for Partials pipeline (new architecture).
     * Uses composable AttemptIterator + PartialStreamingUpdateGenerator.
     */
    private function makeIterativeTransducerHandler(
        InferenceProvider $inferenceProvider,
        CanGenerateResponse $processor,
        CanDetermineRetry $retryPolicy,
    ): CanExecuteStructuredOutput {
        $partialsFactory = new PartialStreamFactory(
            deserializer: $this->responseDeserializer,
            validator: $this->partialResponseValidator,
            transformer: $this->responseTransformer,
            events: $this->events,
        );

        // Stream-level iterator
        $streamIterator = new PartialStreamingUpdateGenerator(
            inferenceProvider: $inferenceProvider,
            partials: $partialsFactory,
        );

        throw new \RuntimeException('This method is deprecated and should not be called. Use makeExecutor() instead which uses the unified pattern.');
    }

    /**
     * @deprecated Now integrated into makeExecutor's unified pattern
     * Create iterative handler for legacy streaming pipeline (new architecture).
     * Uses composable AttemptIterator + StreamingUpdatesGenerator.
     */
    private function makeIterativeGeneratorHandler(
        InferenceProvider $inferenceProvider,
        CanGenerateResponse $processor,
        CanDetermineRetry $retryPolicy,
        StructuredOutputConfig $config,
    ): CanExecuteStructuredOutput {
        throw new \RuntimeException('This method is deprecated and should not be called. Use makeExecutor() instead which uses the unified pattern.');
    }
}
