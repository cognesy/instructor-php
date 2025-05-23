<?php

namespace Cognesy\Instructor\Features\Core;

use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Events\Instructor\InstructorDone;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Utils\Json\Json;
use Exception;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

class StructuredOutputResponse
{
    use Traits\HandlesResultTypecasting;

    private bool $cacheProcessedResponse = true;

    private RequestHandler $requestHandler;
    private EventDispatcherInterface $events;
    private StructuredOutputRequest $request;

    private LLMResponse $cachedResponse;
    private array $cachedResponseStream;

    public function __construct(
        StructuredOutputRequest $request,
        RequestHandler          $requestHandler,
        EventDispatcherInterface $events,
    ) {
        $this->events = $events;
        $this->requestHandler = $requestHandler;
        $this->request = $request;
    }

    /**
     * Executes the request and returns the response
     */
    public function get() : mixed {
        if ($this->request->isStreamed()) {
            return $this->stream()->finalValue();
        }
        $response = $this->getResponse();
        $this->events->dispatch(new InstructorDone(['result' => $response]));
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
        $this->events->dispatch(new InstructorDone(['result' => $response->value()]));
        return $response;
    }

    /**
     * Executes the request and returns the response stream
     */
    public function stream() : StructuredOutputStream {
        // TODO: do we need this? can't we just turn streaming on?
        if (!$this->request->isStreamed()) {
            throw new Exception('StructuredOutput::create()->stream() method requires response streaming: set "stream" = true in the request options.');
        }
        $stream = $this->getStream();
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

    private function getStream() : Generator {
        // RESPONSE CACHING IS DISABLED
        if (!$this->cacheProcessedResponse) {
            yield $this->requestHandler->streamResponseFor($this->request);
            return;
        }
        // RESPONSE CACHING IS ENABLED
        if (!isset($this->cachedResponseStream)) {
            $this->cachedResponseStream = [];
            foreach ($this->requestHandler->streamResponseFor($this->request) as $chunk) {
                $this->cachedResponseStream[] = $chunk;
                yield $chunk;
            }
            return;
        }
        yield from $this->cachedResponseStream;
    }
}