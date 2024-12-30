<?php

namespace Cognesy\Instructor\Features\LLM;

use Closure;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Inference\LLMResponseReceived;
use Cognesy\Instructor\Events\Inference\PartialLLMResponseReceived;
use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Features\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Features\LLM\Data\LLMConfig;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Utils\Json\Json;
use Generator;

class InferenceStream
{
    protected EventDispatcher $events;
    protected EventStreamReader $reader;
    protected Generator $stream;
    protected CanAccessResponse $response;
    protected CanHandleInference $driver;
    protected bool $streamReceived = false;
    protected array $streamEvents = [];
    protected LLMConfig $config;

    protected array $llmResponses = [];
    protected ?LLMResponse $finalLLMResponse = null;
    protected ?PartialLLMResponse $lastPartialLLMResponse = null;
    protected ?Closure $onPartialResponse = null;

    public function __construct(
        CanAccessResponse $response,
        CanHandleInference $driver,
        LLMConfig $config,
        ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->driver = $driver;
        $this->config = $config;
        $this->response = $response;

        $this->stream = $this->response->streamContents();
        $this->reader = new EventStreamReader(
            parser: $this->driver->fromStreamData(...),
            events: $this->events,
        );
    }

    /**
     * @return Generator<PartialLLMResponse>
     */
    public function responses() : Generator {
        foreach ($this->makePartialLLMResponses($this->stream) as $partialLLMResponse) {
            yield $partialLLMResponse;
        }
    }

    /**
     * @return PartialLLMResponse[]
     */
    public function all() : array {
        return $this->getAllPartialLLMResponses($this->stream);
    }

    /**
     * Returns the last partial response for the stream.
     * It will contain accumulated content and finish reason.
     * @return ?LLMResponse
     */
    public function final() : ?LLMResponse {
        return $this->getFinalResponse($this->stream);
    }

    /**
     * Sets a callback to be called when a partial response is received.
     *
     * @param callable $callback
     */
    public function onPartialResponse(callable $callback) : self {
        $this->onPartialResponse = $callback(...);
        return $this;
    }

    // INTERNAL //////////////////////////////////////////////

    /**
     * @param Generator<string> $stream
     * @return ?PartialLLMResponse
     */
    protected function getFinalResponse(Generator $stream) : ?LLMResponse {
        if ($this->finalLLMResponse === null) {
            foreach ($this->makePartialLLMResponses($stream) as $partialResponse) { $tmp = $partialResponse; }
        }
        return $this->finalLLMResponse;
    }

    /**
     * @param Generator<string> $stream
     * @return PartialLLMResponse[]
     */
    protected function getAllPartialLLMResponses(Generator $stream) : array {
        if ($this->finalLLMResponse === null) {
            foreach ($this->makePartialLLMResponses($stream) as $partialResponse) { $tmp = $partialResponse; }
        }
        return $this->llmResponses;
    }

    /**
     * @param Generator<string> $stream
     * @return Generator<PartialLLMResponse>
     */
    private function makePartialLLMResponses(Generator $stream) : Generator {
        $content = '';
        $finishReason = '';
        $this->llmResponses = [];
        $this->lastPartialLLMResponse = null;

        foreach ($this->getEventStream($stream) as $streamEvent) {
            if ($streamEvent === null || $streamEvent === '') {
                continue;
            }
            $data = Json::decode($streamEvent, []);
            $partialResponse = $this->driver->fromStreamResponse($data);
            if ($partialResponse === null) {
                continue;
            }
            $this->llmResponses[] = $partialResponse;

            // add accumulated content and last finish reason
            if ($partialResponse->finishReason !== '') {
                $finishReason = $partialResponse->finishReason;
            }
            $content .= $partialResponse->contentDelta;
            $enrichedResponse = $partialResponse
                ->withContent($content)
                ->withFinishReason($finishReason);
            $this->events->dispatch(new PartialLLMResponseReceived($enrichedResponse));

            $this->lastPartialLLMResponse = $enrichedResponse;
            if ($this->onPartialResponse !== null) {
                ($this->onPartialResponse)($enrichedResponse);
            }
            yield $enrichedResponse;
        }
        $this->finalLLMResponse = LLMResponse::fromPartialResponses($this->llmResponses);
        $this->events->dispatch(new LLMResponseReceived($this->finalLLMResponse));
    }

    /**
     * @return Generator<string|null>
     */
    private function getEventStream(Generator $stream) : Generator {
        if (!$this->streamReceived) {
            foreach($this->streamFromResponse($stream) as $event) {
                $this->streamEvents[] = $event;
                yield $event;
            }
            $this->streamReceived = true;
            return;
        }
        reset($this->streamEvents);
        yield from $this->streamEvents;
    }

    /**
     * @return Generator<string>
     */
    private function streamFromResponse(Generator $stream) : Generator {
        return $this->reader->eventsFrom($stream);
    }
}