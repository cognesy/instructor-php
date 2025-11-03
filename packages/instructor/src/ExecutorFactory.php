<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanExecuteStructuredOutput;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Core\RetryHandler;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Executors\Partials\PartialStreamFactory;
use Cognesy\Instructor\Executors\Partials\PartialStreamingRequestHandler;
use Cognesy\Instructor\Executors\Streaming\PartialGen\GeneratePartialsFromJson;
use Cognesy\Instructor\Executors\Streaming\PartialGen\GeneratePartialsFromToolCalls;
use Cognesy\Instructor\Executors\Streaming\StreamingRequestHandler;
use Cognesy\Instructor\Executors\Sync\SyncRequestHandler;
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

    public function makeExecutor(StructuredOutputExecution $execution) : CanExecuteStructuredOutput {
        return match(true) {
            $execution->isStreamed() => $this->makeStreamingHandler(
                inferenceProvider: $this->makeInferenceProvider(),
                processor: $this->makeResponseProcessor(),
                retryHandler: $this->makeRetryHandler(),
                config: $execution->config(),
            ),
            default => $this->makeSyncHandler(
                inferenceProvider: $this->makeInferenceProvider(),
                processor: $this->makeResponseProcessor(),
                retryHandler: $this->makeRetryHandler(),
            ),
        };
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////

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
        };
    }

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
            processor: $processor,
            retryHandler: $retryHandler,
        );
    }

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

    private function makeResponseProcessor(): CanGenerateResponse {
        return new ResponseGenerator(
            $this->responseDeserializer,
            $this->responseValidator,
            $this->responseTransformer,
            $this->events,
        );
    }
}
