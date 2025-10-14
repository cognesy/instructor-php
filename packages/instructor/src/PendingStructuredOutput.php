<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Config\PartialsGeneratorConfig;
use Cognesy\Instructor\Contracts\CanExecuteStructuredOutput;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Core\ResponseNormalizer;
use Cognesy\Instructor\Core\SyncRequestHandler;
use Cognesy\Instructor\Core\ValidationRetryHandler;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\Partials\PartialStreamFactory;
use Cognesy\Instructor\Partials\PartialStreamingRequestHandler;
use Cognesy\Instructor\Streaming\StructuredOutputStream;
use Cognesy\Instructor\Traits\HandlesResultTypecasting;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Json\Json;
use RuntimeException;

// use Cognesy\Instructor\Streaming\PartialGen\GeneratePartialsFromJson;
// use Cognesy\Instructor\Streaming\PartialGen\GeneratePartialsFromToolCalls;
// use Cognesy\Instructor\Streaming\StreamingRequestHandler;

/**
 * @template TResponse
 */
class PendingStructuredOutput
{
    use HandlesResultTypecasting;

    private readonly CanHandleEvents $events;
    private readonly CanExecuteStructuredOutput $syncHandler;
    private readonly CanExecuteStructuredOutput $streamingHandler;
    private readonly ResponseDeserializer $responseDeserializer;
    private readonly ResponseValidator $responseValidator;
    private readonly ResponseTransformer $responseTransformer;

    private StructuredOutputExecution $execution;
    private readonly LLMProvider $llmProvider;
    private readonly ?HttpClient $httpClient;

    private readonly bool $cacheProcessedResponse;
    private ?InferenceResponse $cachedResponse = null;

    public function __construct(
        StructuredOutputExecution $execution,
        ResponseDeserializer     $responseDeserializer,
        ResponseValidator        $responseValidator,
        ResponseTransformer      $responseTransformer,
        LLMProvider              $llmProvider,
        CanHandleEvents          $events,
        ?HttpClient              $httpClient = null,
    ) {
        $this->cacheProcessedResponse = true;

        $this->execution = $execution;
        $this->events = $events;
        $this->responseDeserializer = $responseDeserializer;
        $this->responseValidator = $responseValidator;
        $this->responseTransformer = $responseTransformer;
        $this->llmProvider = $llmProvider;
        $this->httpClient = $httpClient;
        $this->syncHandler = $this->makeSyncHandler();
        $this->streamingHandler = $this->makeStreamingHandler($execution->config()->outputMode());
    }

    /**
     * Executes the request and returns the parsed value
     *
     * @return TResponse
     */
    public function get() : mixed {
        return match(true) {
            $this->execution->isStreamed() => $this->stream()->finalValue(),
            default => $this->getResponse()->value(),
        };
    }

    public function toJsonObject() : Json {
        return match(true) {
            $this->execution->isStreamed() => $this->stream()->finalResponse()->findJsonData($this->execution->outputMode()),
            default => $this->getResponse()->findJsonData($this->execution->outputMode())
        };
    }

    public function toJson() : string {
        return $this->toJsonObject()->toString();
    }

    public function toArray() : array {
        return $this->toJsonObject()->toArray();
    }

    /**
     * Executes the request and returns LLM response object
     */
    public function response() : InferenceResponse {
        return $this->getResponse();
    }

    public function execution() : StructuredOutputExecution {
        return $this->execution;
    }

    /**
     * Executes the request and returns the response stream
     *
     * @return StructuredOutputStream<TResponse>
     */
    public function stream() : StructuredOutputStream {
        $this->execution = $this->execution->withStreamed();
        return new StructuredOutputStream(
            $this->execution,
            $this->streamingHandler,
            $this->events,
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    private function getResponse() : InferenceResponse {
        $this->events->dispatch(new StructuredOutputStarted(['request' => $this->execution->request()->toArray()]));

        // RESPONSE CACHING = IS DISABLED
        if (!$this->cacheProcessedResponse) {
            foreach ($this->syncHandler->nextUpdate($this->execution) as $exec) {
                $this->execution = $exec;
            }
            $response = $this->execution->inferenceResponse();
            if ($response === null) {
                throw new RuntimeException('Failed to get inference response');
            }
            $this->events->dispatch(new StructuredOutputResponseGenerated(['value' => json_encode($response->value())]));
            return $response;
        }

        // RESPONSE CACHING = IS ENABLED
        if ($this->cachedResponse === null) {
            foreach ($this->syncHandler->nextUpdate($this->execution) as $exec) {
                $this->execution = $exec;
            }
            $this->cachedResponse = $this->execution->inferenceResponse();
            if ($this->cachedResponse === null) {
                throw new RuntimeException('Failed to get inference response');
            }
        }

        $this->events->dispatch(new StructuredOutputResponseGenerated([
            'value' => json_encode($this->cachedResponse->value()),
            'cached' => true,
        ]));
        return $this->cachedResponse;
    }

    private function makeSyncHandler(): CanExecuteStructuredOutput {
        $inferenceProvider = $this->makeInferenceProvider();
        $retryHandler = $this->makeRetryHandler();

        return new SyncRequestHandler(
            inferenceProvider: $inferenceProvider,
            normalizer: new ResponseNormalizer(),
            retryHandler: $retryHandler,
        );
    }

    private function makeStreamingHandler(OutputMode $mode): CanExecuteStructuredOutput {
        $inferenceProvider = $this->makeInferenceProvider();
        $retryHandler = $this->makeRetryHandler();

//        $partialsGenerator = match($mode) {
//            OutputMode::Tools => new GeneratePartialsFromToolCalls(
//                $this->responseDeserializer,
//                $this->responseTransformer,
//                $this->events,
//            ),
//            default => new GeneratePartialsFromJson(
//                $this->responseDeserializer,
//                $this->responseTransformer,
//                $this->events,
//            ),
//        };

        $partialsFactory = new PartialStreamFactory(
            deserializer: $this->responseDeserializer,
            transformer: $this->responseTransformer,
            events: $this->events,
            config: new PartialsGeneratorConfig(),
        );

        return new PartialStreamingRequestHandler(
            inferenceProvider: $inferenceProvider,
            partials: $partialsFactory,
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

    private function makeRetryHandler(): ValidationRetryHandler {
        return new ValidationRetryHandler(
            responseGenerator: new ResponseGenerator(
                $this->responseDeserializer,
                $this->responseValidator,
                $this->responseTransformer,
                $this->events,
            ),
            events: $this->events,
        );
    }
}
