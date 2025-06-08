<?php

namespace Cognesy\Instructor;

use Cognesy\Events\Contracts\CanRegisterEventListeners;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\PartialsGenerator;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Core\Traits\HandlesResultTypecasting;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputDone;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\LLMProvider;
use Cognesy\Utils\Json\Json;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

class PendingStructuredOutput
{
    use HandlesResultTypecasting;

    private bool $cacheProcessedResponse = true;

    private RequestHandler $requestHandler;
    private EventDispatcherInterface $events;
    private StructuredOutputRequest $request;

    private LLMResponse $cachedResponse;
    private array $cachedResponseStream;

    private ResponseDeserializer $responseDeserializer;
    private ResponseValidator $responseValidator;
    private ResponseTransformer $responseTransformer;
    private CanRegisterEventListeners $listener;
    private LLMProvider $llmProvider;
    private StructuredOutputConfig $config;

    public function __construct(
        StructuredOutputRequest $request,
        ResponseDeserializer $responseDeserializer,
        ResponseValidator $responseValidator,
        ResponseTransformer $responseTransformer,
        LLMProvider $llmProvider,
        StructuredOutputConfig $config,
        EventDispatcherInterface $events,
        CanRegisterEventListeners $listener,
    ) {
        $this->request = $request;
        $this->events = $events;
        $this->listener = $listener;
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
        if ($this->request->isStreamed()) {
            return $this->stream()->finalValue();
        }
        $response = $this->getResponse();
        $this->events->dispatch(new StructuredOutputDone(['result' => $response]));
        return $response->value();
    }

    public function toJsonObject() : Json {
        $response = match(true) {
            $this->request->isStreamed() => $this->stream()->finalResponse(),
            default => $this->getResponse()
        };
        return $response->findJsonData($this->request->mode());
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
    public function response() : LLMResponse {
        $response = $this->getResponse();
        $this->events->dispatch(new StructuredOutputDone(['result' => $response->value()]));
        return $response;
    }

    /**
     * Executes the request and returns the response stream
     */
    public function stream() : StructuredOutputStream {
        $stream = $this->getStream($this->request->withStreamed());
        return new StructuredOutputStream($stream, $this->events);
    }

    // INTERNAL /////////////////////////////////////////////////

    private function getResponse() : LLMResponse {
        // RESPONSE CACHING IS DISABLED
        if (!$this->cacheProcessedResponse) {
            return $this->requestHandler->responseFor($this->request);
        }
        // RESPONSE CACHING IS ENABLED
        if (!isset($this->cachedResponse)) {
            $this->cachedResponse = $this->requestHandler->responseFor($this->request);
        }
        return $this->cachedResponse;
    }

    private function getStream(StructuredOutputRequest $request) : Generator {
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
            request: $this->request,
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
            llm: $this->llmProvider,
            events: $this->events,
            listener: $this->listener,
        );
    }
}