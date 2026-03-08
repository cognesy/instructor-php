<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\CanDetermineRetry;
use Cognesy\Instructor\Contracts\CanEmitStreamingUpdates;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Core\StreamingExecutionDriver;
use Cognesy\Instructor\Core\SyncExecutionDriver;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\RetryPolicy\DefaultRetryPolicy;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidateResponse;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;

final class ExecutionDriverFactory
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

    public function makeExecutionDriver(StructuredOutputExecution $execution): CanEmitStreamingUpdates {
        return match (true) {
            $execution->isStreamed() => $this->makeStreamingExecutionDriver($execution),
            default => $this->makeSyncExecutionDriver($execution),
        };
    }

    public function makeStreamingExecutionDriver(StructuredOutputExecution $execution): StreamingExecutionDriver {
        return new StreamingExecutionDriver(
            execution: $execution,
            inferenceProvider: $this->makeInferenceProvider(),
            deserializer: $this->responseDeserializer,
            transformer: $this->responseTransformer,
            responseGenerator: $this->makeResponseGenerator(),
            retryPolicy: $this->makeRetryPolicy(),
            events: $this->events,
        );
    }

    public function makeSyncExecutionDriver(StructuredOutputExecution $execution): SyncExecutionDriver {
        return new SyncExecutionDriver(
            execution: $execution,
            inferenceProvider: $this->makeInferenceProvider(),
            responseGenerator: $this->makeResponseGenerator(),
            retryPolicy: $this->makeRetryPolicy(),
        );
    }

    private function makeInferenceProvider(): InferenceProvider {
        return new InferenceProvider(
            inference: $this->inference,
            requestMaterializer: new RequestMaterializer(),
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
