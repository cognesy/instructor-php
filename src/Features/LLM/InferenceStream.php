<?php

namespace Cognesy\Instructor\Features\LLM;

use Cognesy\Instructor\Events\EventDispatcher;
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
        $this->reader = new EventStreamReader($this->driver->getData(...), $this->events);
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
     * @return ?PartialLLMResponse
     */
    public function final() : ?PartialLLMResponse {
        return $this->finalResponse($this->stream);
    }

    // INTERNAL //////////////////////////////////////////////

    /**
     * @param Generator<PartialLLMResponse> $partialResponses
     * @return ?PartialLLMResponse
     */
    protected function finalResponse(Generator $partialResponses) : ?PartialLLMResponse {
        $lastPartial = null;
        foreach ($partialResponses as $partialResponse) {
            $lastPartial = $partialResponse;
        }
        return $lastPartial;
    }

    /**
     * @param Generator<string> $stream
     * @return PartialLLMResponse[]
     */
    protected function getAllPartialLLMResponses(Generator $stream) : array {
        $partialResponses = [];
        foreach ($this->makePartialLLMResponses($stream) as $partialResponse) {
            $partialResponses[] = $partialResponse;
        }
        return $partialResponses;
    }

    /**
     * @param Generator<string> $stream
     * @return Generator<PartialLLMResponse>
     */
    private function makePartialLLMResponses(Generator $stream) : Generator {
        $content = '';
        $finishReason = '';
        foreach ($this->getEventStream($stream) as $streamEvent) {
            if ($streamEvent === false) {
                continue;
            }
            $data = Json::decode($streamEvent, []);
            $partialResponse = $this->makePartialLLMResponse($data);
            if ($partialResponse === null) {
                continue;
            }
            if ($partialResponse->finishReason !== '') {
                $finishReason = $partialResponse->finishReason;
            }
            $content .= $partialResponse->contentDelta;
            // add accumulated content and last finish reason
            $enrichedResponse = $partialResponse
                ->withContent($content)
                ->withFinishReason($finishReason);
            $this->events->dispatch(new PartialLLMResponseReceived($enrichedResponse));
            yield $enrichedResponse;
        }
    }

    private function makePartialLLMResponse(array $data) : ?PartialLLMResponse {
        return $this->driver->toPartialLLMResponse($data);
    }

    /**
     * @return Generator<string>
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