<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\PartialsGenerator;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Core\Traits\HandlesResultTypecasting;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Json\Json;
use Generator;

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
    private readonly StructuredOutputRequest $request;
    private readonly StructuredOutputConfig $config;
    private readonly LLMProvider $llmProvider;
    private readonly bool $cacheProcessedResponse;
    private readonly ?HttpClient $httpClient;

    private InferenceResponse $cachedResponse;
    private array $cachedResponseStream;

    public function __construct(
        StructuredOutputExecution $execution,
        ResponseDeserializer     $responseDeserializer,
        ResponseValidator        $responseValidator,
        ResponseTransformer      $responseTransformer,
        LLMProvider              $llmProvider,
        StructuredOutputConfig   $config,
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
        $this->config = $config;
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

    /**
     * Executes the request and returns the response stream
     *
     * @return StructuredOutputStream<TResponse>
     */
    public function stream() : StructuredOutputStream {
        $this->execution->withStreamed();
        $stream = $this->getStream($this->execution);
        return new StructuredOutputStream($stream, $this->events);
    }

    // INTERNAL /////////////////////////////////////////////////

    private function getResponse() : InferenceResponse {
        $this->events->dispatch(new StructuredOutputStarted(['request' => $this->execution->request()->toArray()]));

        // RESPONSE CACHING IS DISABLED
        if (!$this->cacheProcessedResponse) {
            $execution = $this->requestHandler->responseFor($this->execution);
            $response = $execution->inferenceResponse();
            $this->events->dispatch(new StructuredOutputResponseGenerated(['value' => json_encode($response?->value())]));
            return $response;
        }

        // RESPONSE CACHING IS ENABLED
        if (!isset($this->cachedResponse)) {
            $this->cachedResponse = $this->requestHandler->responseFor($this->execution)->inferenceResponse();
        }

        $this->events->dispatch(new StructuredOutputResponseGenerated(['result' => json_encode($this->cachedResponse), 'cached' => true]));
        return $this->cachedResponse;
    }

    private function getStream(StructuredOutputExecution $execution) : Generator {
        $this->events->dispatch(new StructuredOutputStarted(['request' => $execution->request()->toArray()]));

        // RESPONSE CACHING IS DISABLED
        if (!$this->cacheProcessedResponse) {
            yield $this->requestHandler->streamResponseFor($execution);
            return;
        }

        // RESPONSE CACHING IS ENABLED
        if (!isset($this->cachedResponseStream)) {
            $this->cachedResponseStream = [];
            foreach ($this->requestHandler->streamResponseFor($execution) as $chunk) {
                $this->cachedResponseStream[] = $chunk;
                yield $chunk;
            }
            return;
        }

        yield from $this->cachedResponseStream;
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
            requestMaterializer: new RequestMaterializer($this->config),
            llmProvider: $this->llmProvider,
            events: $this->events,
            httpClient: $this->httpClient,
        );
    }
}
