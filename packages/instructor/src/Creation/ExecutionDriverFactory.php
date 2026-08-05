<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanDetermineRetry;
use Cognesy\Instructor\Contracts\CanDriveExecution;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Core\ResponseMaterializer;
use Cognesy\Instructor\Core\StreamingExecutionDriver;
use Cognesy\Instructor\Core\SyncExecutionDriver;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\ResponseExtractor;
use Cognesy\Instructor\RetryPolicy\DefaultRetryPolicy;
use Cognesy\Instructor\Transformation\Contracts\CanTransformData;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\Contracts\CanValidateResponse;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Instructor\Validation\Validators\SymfonyValidator;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;

final class ExecutionDriverFactory
{
    private readonly CanCreateInference $inference;
    private readonly CanExtractResponse $extractor;
    private readonly CanHandleEvents $events;
    private readonly CanMaterializeRequest $requestMaterializer;

    /**
     * The one materializer for this factory. Both the streaming driver's preview path and
     * the response generator's finalisation path resolve values through this instance, so
     * they cannot drift into materializing the same data by different rules.
     */
    private readonly ResponseMaterializer $materializer;

    public function __construct(
        CanCreateInference $inference,
        CanDeserializeResponse $responseDeserializer,
        CanValidateResponse $responseValidator,
        CanTransformResponse $responseTransformer,
        CanHandleEvents $events,
        CanExtractResponse $extractor,
        CanMaterializeRequest $requestMaterializer,
    ) {
        $this->inference = $inference;
        $this->extractor = $extractor;
        $this->events = $events;
        $this->requestMaterializer = $requestMaterializer;
        $this->materializer = new ResponseMaterializer(
            deserializer: $responseDeserializer,
            validator: $responseValidator,
            transformer: $responseTransformer,
        );
    }

    /**
     * Builds the factory from the runtime's raw, partly-unset collaborators: applies the
     * default deserializer/validator/extractor and wraps each in its response-stage
     * decorator. The explicit constructor remains the seam for injecting fully-built
     * collaborators (see tests/Unit/Creation/ExecutionDriverFactoryTest.php).
     */
    public static function fromParts(
        CanHandleEvents $events,
        StructuredOutputConfig $config,
        CanCreateInference $inference,
        CanMaterializeRequest $requestMaterializer,
        ?CanValidateObject $validator = null,
        ?CanTransformData $transformer = null,
        ?CanDeserializeClass $deserializer = null,
        ?CanExtractResponse $extractor = null,
    ): self {
        return new self(
            inference: $inference,
            responseDeserializer: new ResponseDeserializer(
                events: $events,
                deserializer: $deserializer ?? new SymfonyDeserializer(),
                config: $config,
            ),
            responseValidator: new ResponseValidator(
                events: $events,
                validator: $validator ?? new SymfonyValidator(),
                config: $config,
            ),
            responseTransformer: new ResponseTransformer(
                events: $events,
                transformer: $transformer,
            ),
            events: $events,
            extractor: $extractor ?? new ResponseExtractor(events: $events),
            requestMaterializer: $requestMaterializer,
        );
    }

    public function makeExecutionDriver(StructuredOutputExecution $execution): CanDriveExecution {
        return match (true) {
            $execution->isStreamed() => $this->makeStreamingExecutionDriver($execution),
            default => $this->makeSyncExecutionDriver($execution),
        };
    }

    public function makeStreamingExecutionDriver(StructuredOutputExecution $execution): StreamingExecutionDriver {
        return new StreamingExecutionDriver(
            execution: $execution,
            inferenceProvider: $this->makeInferenceProvider(),
            materializer: $this->materializer,
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
            events: $this->events,
        );
    }

    private function makeInferenceProvider(): InferenceProvider {
        return new InferenceProvider(
            inference: $this->inference,
            requestMaterializer: $this->requestMaterializer,
        );
    }

    private function makeRetryPolicy(): CanDetermineRetry {
        return new DefaultRetryPolicy(
            events: $this->events,
        );
    }

    private function makeResponseGenerator(): CanGenerateResponse {
        return new ResponseGenerator(
            $this->materializer,
            $this->extractor,
        );
    }
}
