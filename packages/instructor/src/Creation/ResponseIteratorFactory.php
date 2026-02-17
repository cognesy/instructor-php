<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation;

use Closure;
use Cognesy\Events\Contracts\CanHandleEvents;
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
use Cognesy\Instructor\Extraction\Contracts\CanBufferContent;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Contracts\CanProvideContentBuffer;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\ModularStreamFactory;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\ModularUpdateGenerator;
use Cognesy\Instructor\ResponseIterators\Sync\SyncUpdateGenerator;
use Cognesy\Instructor\RetryPolicy\DefaultRetryPolicy;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidateResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;

class ResponseIteratorFactory
{
    private readonly CanCreateInference $inference;
    private readonly CanDeserializeResponse $responseDeserializer;
    private readonly CanValidateResponse $responseValidator;
    private readonly CanTransformResponse $responseTransformer;
    private readonly CanExtractResponse $extractor;
    private readonly CanHandleEvents $events;

    public function __construct(
        CanCreateInference $inference,
        CanDeserializeResponse $responseDeserializer,
        CanValidateResponse $responseValidator,
        CanTransformResponse $responseTransformer,
        CanHandleEvents $events,
        CanExtractResponse $extractor,
    ) {
        $this->inference = $inference;
        $this->responseDeserializer = $responseDeserializer;
        $this->responseValidator = $responseValidator;
        $this->responseTransformer = $responseTransformer;
        $this->extractor = $extractor;
        $this->events = $events;
    }

    public function makeExecutor(StructuredOutputExecution $execution) : CanHandleStructuredOutputAttempts {
        $streamIterator = match(true) {
            $execution->isStreamed() => $this->makeStreamingIterator(),
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

    private function makeStreamingIterator(): CanStreamStructuredOutputUpdates {
        $factory = new ModularStreamFactory(
            deserializer: $this->responseDeserializer,
            transformer: $this->responseTransformer,
            events: $this->events,
            bufferFactory: $this->makeBufferFactory(),
        );

        return new ModularUpdateGenerator(
            inferenceProvider: $this->makeInferenceProvider(),
            factory: $factory,
        );
    }

    /**
     * Create buffer factory for streaming extraction.
     *
     * Uses the extractor's CanProvideContentBuffer implementation if available.
     *
     * @return Closure(OutputMode): CanBufferContent|null Factory that creates buffer based on OutputMode
     */
    private function makeBufferFactory(): ?Closure
    {
        if ($this->extractor instanceof CanProvideContentBuffer) {
            $extractor = $this->extractor;
            return fn(OutputMode $mode): CanBufferContent => $extractor->makeContentBuffer($mode);
        }
        return null;
    }

    private function makeInferenceProvider(): InferenceProvider {
        return new InferenceProvider(
            inference: $this->inference,
            requestMaterializer: new RequestMaterializer(),
            events: $this->events,
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
