<?php

namespace Cognesy\Polyglot\LLM;

use Closure;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Polyglot\LLM\Events\LLMResponseReceived;
use Cognesy\Polyglot\LLM\Events\PartialLLMResponseReceived;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Json\Json;
use Generator;

/**
 * The InferenceStream class is responsible for handling and processing streamed responses
 * from language models in a structured and event-driven manner. It allows for real-time
 * processing of incoming data and supports partial and cumulative responses.
 */
class InferenceStream
{
    protected EventDispatcher $events;
    protected EventStreamReader $reader;
    protected Generator $stream;
    protected HttpClientResponse $response;
    protected CanHandleInference $driver;
    protected bool $streamReceived = false;
    protected array $streamEvents = [];
    protected LLMConfig $config;

    protected array $llmResponses = [];
    protected ?LLMResponse $finalLLMResponse = null;
    protected ?PartialLLMResponse $lastPartialLLMResponse = null;
    protected ?Closure $onPartialResponse = null;

    public function __construct(
        HttpClientResponse $response,
        CanHandleInference $driver,
        LLMConfig          $config,
        ?EventDispatcher   $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->driver = $driver;
        $this->config = $config;
        $this->response = $response;

        $this->stream = $this->response->stream();
        $this->reader = new EventStreamReader(
            parser: $this->driver->fromStreamData(...),
            events: $this->events,
        );
    }

    /**
     * Generates and yields partial LLM responses from the given stream.
     *
     * @return Generator<\Cognesy\Polyglot\LLM\Data\PartialLLMResponse> A generator yielding partial LLM responses.
     */
    public function responses() : Generator {
        foreach ($this->makePartialLLMResponses($this->stream) as $partialLLMResponse) {
            yield $partialLLMResponse;
        }
    }

    /**
     * Retrieves all partial LLM responses from the given stream.
     *
     * @return \Cognesy\Polyglot\LLM\Data\PartialLLMResponse[] An array of all partial LLM responses.
     */
    public function all() : array {
        return $this->getAllPartialLLMResponses($this->stream);
    }

    /**
     * Returns the last partial response for the stream.
     * It will contain accumulated content and finish reason.
     *
     * @return ?\Cognesy\Polyglot\LLM\Data\LLMResponse
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
     * Retrieves the final LLM response from the given stream of partial responses.
     *
     * @param Generator<PartialLLMResponse> $stream A generator yielding raw partial LLM response strings.
     * @return \Cognesy\Polyglot\LLM\Data\LLMResponse|null The final LLMResponse object or null if not available.
     */
    protected function getFinalResponse(Generator $stream) : ?LLMResponse {
        if ($this->finalLLMResponse === null) {
            foreach ($this->makePartialLLMResponses($stream) as $partialResponse) { $tmp = $partialResponse; }
        }
        return $this->finalLLMResponse;
    }

    /**
     * Retrieves all partial LLM responses from the provided generator stream.
     *
     * @param Generator<string> $stream The generator stream producing raw partial LLM response strings.
     * @return PartialLLMResponse[] An array containing all PartialLLMResponse objects.
     */
    protected function getAllPartialLLMResponses(Generator $stream) : array {
        if ($this->finalLLMResponse === null) {
            foreach ($this->makePartialLLMResponses($stream) as $partialResponse) { $tmp = $partialResponse; }
        }
        return $this->llmResponses;
    }

    /**
     * Processes the given stream to generate partial LLM responses and enriches them with accumulated content and finish reason.
     *
     * @param Generator<string> $stream The stream to be processed to extract and enrich partial LLM responses.
     * @return Generator<\Cognesy\Polyglot\LLM\Data\PartialLLMResponse> A generator yielding enriched PartialLLMResponse objects.
     */
    private function makePartialLLMResponses(Generator $stream) : Generator {
        $content = '';
        $reasoningContent = '';
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
            $reasoningContent .= $partialResponse->reasoningContentDelta;
            $enrichedResponse = $partialResponse
                ->withContent($content)
                ->withReasoningContent($reasoningContent)
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
     * Processes and retrieves events from the provided stream generator.
     *
     * @param Generator<string|null> $stream The input generator stream containing events.
     *
     * @return Generator<string> A generator yielding individual events from the processed stream.
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
     * Processes a stream of data and returns a generator of parsed events.
     *
     * @param Generator<string> $stream The input data stream to be processed.
     * @return Generator<string|null> A generator yielding parsed events from the input stream.
     */
    private function streamFromResponse(Generator $stream) : Generator {
        return $this->reader->eventsFrom($stream);
    }
}