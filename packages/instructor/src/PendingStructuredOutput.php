<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\Streaming\PartialsGenerator;
use Cognesy\Instructor\Traits\HandlesResultTypecasting;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Json\Json;

/**
 * @template TResponse
 */
class PendingStructuredOutput
{
    use HandlesResultTypecasting;

    private readonly CanHandleEvents $events;
    private readonly RequestHandler $requestHandler;
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
        $this->requestHandler = $this->makeRequestHandler();
    }

    /**
     * Executes the request and returns the parsed value
     *
     * @return TResponse
     */
    public function get() : mixed {
        return match(true) {
            $this->execution->request()->isStreamed() => $this->stream()->finalValue(),
            default => $this->getResponse()->value(),
        };
    }

    public function toJsonObject() : Json {
        return match(true) {
            $this->execution->request()->isStreamed() => $this->stream()->finalResponse()->findJsonData($this->execution->outputMode()),
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
            $this->requestHandler,
            $this->events,
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    private function getResponse() : InferenceResponse {
        $this->events->dispatch(new StructuredOutputStarted(['request' => $this->execution->request()->toArray()]));

        // RESPONSE CACHING = IS DISABLED
        if (!$this->cacheProcessedResponse) {
            $this->execution = $this->requestHandler->executionResultFor($this->execution);
            $response = $this->execution->inferenceResponse();
            if ($response === null) {
                throw new \RuntimeException('Failed to get inference response');
            }
            $this->events->dispatch(new StructuredOutputResponseGenerated(['value' => json_encode($response->value())]));
            return $response;
        }

        // RESPONSE CACHING = IS ENABLED
        if ($this->cachedResponse === null) {
            $this->execution = $this->requestHandler->executionResultFor($this->execution);
            $this->cachedResponse = $this->execution->inferenceResponse();
            if ($this->cachedResponse === null) {
                throw new \RuntimeException('Failed to get inference response');
            }
        }

        $this->events->dispatch(new StructuredOutputResponseGenerated([
            'value' => json_encode($this->cachedResponse->value()),
            'cached' => true,
        ]));
        return $this->cachedResponse;
    }

    private function makeRequestHandler() : RequestHandler {
        return new RequestHandler(
            responseGenerator: new ResponseGenerator(
                $this->responseDeserializer,
                $this->responseValidator,
                $this->responseTransformer,
                $this->events,
            ),
            partialsGenerator: new PartialsGenerator(
                $this->responseDeserializer,
                $this->responseTransformer,
                $this->events,
            ),
            requestMaterializer: new RequestMaterializer(),
            llmProvider: $this->llmProvider,
            events: $this->events,
            httpClient: $this->httpClient,
        );
    }
}
