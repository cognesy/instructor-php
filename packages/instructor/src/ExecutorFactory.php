<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Config\PartialsGeneratorConfig;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanExecuteStructuredOutput;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Core\RetryHandler;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Executors\Partials\PartialStreamFactory;
use Cognesy\Instructor\Executors\Partials\PartialStreamingRequestHandler;
use Cognesy\Instructor\Executors\Streaming\PartialGen\GeneratePartialsFromJson;
use Cognesy\Instructor\Executors\Streaming\PartialGen\GeneratePartialsFromToolCalls;
use Cognesy\Instructor\Executors\Streaming\StreamingRequestHandler;
use Cognesy\Instructor\Executors\Sync\SyncRequestHandler;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;

class ExecutorFactory
{
    public function __construct(
        private readonly LLMProvider $llmProvider,
        private readonly ResponseDeserializer $responseDeserializer,
        private readonly ResponseValidator $responseValidator,
        private readonly ResponseTransformer $responseTransformer,
        private readonly CanHandleEvents $events,
        private readonly ?HttpClient $httpClient = null,
    ) {}

    public function makeExecutor(StructuredOutputExecution $execution) : CanExecuteStructuredOutput {
        return match(true) {
            $execution->isStreamed() => $this->makeStreamingHandler(
                inferenceProvider: $this->makeInferenceProvider(),
                processor: $this->makeResponseProcessor(),
                retryHandler: $this->makeRetryHandler(),
                config: $execution->config(),
                partialsConfig: new PartialsGeneratorConfig(),
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
        PartialsGeneratorConfig $partialsConfig,
    ): CanExecuteStructuredOutput {
        return match($config->streamingDriver) {
            'partials' => $this->makeTransducerHandler(
                inferenceProvider: $inferenceProvider,
                processor: $processor,
                retryHandler: $retryHandler,
                config: $partialsConfig,
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
                $this->responseTransformer,
                $this->events,
            ),
            default => new GeneratePartialsFromJson(
                $this->responseDeserializer,
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
        PartialsGeneratorConfig $config,
    ) : CanExecuteStructuredOutput {
        $partialsFactory = new PartialStreamFactory(
            deserializer: $this->responseDeserializer,
            transformer: $this->responseTransformer,
            events: $this->events,
            config: $config,
        );
        return new PartialStreamingRequestHandler(
            inferenceProvider: $inferenceProvider,
            partials: $partialsFactory,
            processor: $processor,
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
