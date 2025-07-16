<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\PartialsGenerator;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Core\Traits\HandlesResultTypecasting;
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

class PendingStructuredOutput
{
    use HandlesResultTypecasting;

    private readonly CanHandleEvents $events;
    private readonly RequestHandler $requestHandler;
    private readonly ResponseDeserializer $responseDeserializer;
    private readonly ResponseValidator $responseValidator;
    private readonly ResponseTransformer $responseTransformer;

    private readonly StructuredOutputRequest $request;
    private readonly StructuredOutputConfig $config;
    private readonly LLMProvider $llmProvider;
    private readonly bool $cacheProcessedResponse;

    private InferenceResponse $cachedResponse;
    private array $cachedResponseStream;

    public function __construct(
        StructuredOutputRequest  $request,
        ResponseDeserializer     $responseDeserializer,
        ResponseValidator        $responseValidator,
        ResponseTransformer      $responseTransformer,
        LLMProvider              $llmProvider,
        StructuredOutputConfig   $config,
        CanHandleEvents          $events,
    ) {
        $this->cacheProcessedResponse = true;
        $this->request = $request;
        $this->events = $events;
        $this->responseDeserializer = $responseDeserializer;
        $this->responseValidator = $responseValidator;
        $this->responseTransformer = $responseTransformer;
        $this->llmProvider = $llmProvider;
        $this->config = $config;
        $this->requestHandler = $this->makeRequestHandler();
    }

    /**
     * Executes the request and returns the response
     */
    public function get() : mixed {
        return match(true) {
            $this->request->isStreamed() => $this->stream()->finalValue(),
            default => $this->getResponse()->value(),
        };
    }

    public function toJsonObject() : Json {
        return match(true) {
            $this->request->isStreamed() => $this->stream()->finalResponse()->findJsonData($this->request->mode()),
            default => $this->getResponse()->findJsonData($this->request->mode())
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
     */
    public function stream() : StructuredOutputStream {
        $stream = $this->getStream($this->request->withStreamed());
        return new StructuredOutputStream($stream, $this->events);
    }

    // INTERNAL /////////////////////////////////////////////////

    private function getResponse() : InferenceResponse {
        $this->events->dispatch(new StructuredOutputStarted(['request' => $this->request->toArray()]));

        // RESPONSE CACHING IS DISABLED
        if (!$this->cacheProcessedResponse) {
            $response = $this->requestHandler->responseFor($this->request);
            $this->events->dispatch(new StructuredOutputResponseGenerated(['value' => json_encode($response->value())]));
            return $response;
        }

        // RESPONSE CACHING IS ENABLED
        if (!isset($this->cachedResponse)) {
            $this->cachedResponse = $this->requestHandler->responseFor($this->request);
        }

        $this->events->dispatch(new StructuredOutputResponseGenerated(['result' => json_encode($this->cachedResponse), 'cached' => true]));
        return $this->cachedResponse;
    }

    private function getStream(StructuredOutputRequest $request) : Generator {
        $this->events->dispatch(new StructuredOutputStarted(['request' => $this->request->toArray()]));

        // RESPONSE CACHING IS DISABLED
        if (!$this->cacheProcessedResponse) {
            yield $this->requestHandler->streamResponseFor($request);
            return;
        }

        // RESPONSE CACHING IS ENABLED
        if (!isset($this->cachedResponseStream)) {
            $this->cachedResponseStream = [];
            foreach ($this->requestHandler->streamResponseFor($request) as $chunk) {
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
        );
    }
}