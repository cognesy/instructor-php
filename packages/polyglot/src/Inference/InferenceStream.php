<?php

namespace Cognesy\Polyglot\Inference;

use Closure;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Events\InferenceFailed;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\Events\PartialInferenceResponseCreated;
use Cognesy\Polyglot\Inference\Utils\EventStreamReader;
use Cognesy\Utils\Json\Json;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The InferenceStream class is responsible for handling and processing streamed responses
 * from language models in a structured and event-driven manner. It allows for real-time
 * processing of incoming data and supports partial and cumulative responses.
 */
class InferenceStream
{
    protected EventDispatcherInterface $events;
    protected EventStreamReader $reader;
    protected Generator $stream;
    protected HttpClientResponse $httpResponse;
    protected CanHandleInference $driver;
    protected bool $streamReceived = false;
    protected array $streamEvents = [];

    protected array $inferenceResponses = [];
    protected ?InferenceResponse $finalInferenceResponse = null;
    protected ?PartialInferenceResponse $lastPartialInferenceResponse = null;
    protected ?Closure $onPartialResponse = null;

    public function __construct(
        HttpClientResponse        $httpResponse,
        CanHandleInference        $driver,
        EventDispatcherInterface  $eventDispatcher,
    ) {
        $this->events = $eventDispatcher;
        $this->driver = $driver;
        $this->httpResponse = $httpResponse;

        $this->stream = $this->httpResponse->stream();
        $this->reader = new EventStreamReader(
            parser: $this->driver->fromStreamData(...),
            events: $this->events,
        );
    }

    /**
     * Generates and yields partial LLM responses from the given stream.
     *
     * @return Generator<PartialInferenceResponse> A generator yielding partial LLM responses.
     */
    public function responses() : Generator {
        foreach ($this->tryMakePartialInferenceResponses($this->stream) as $partialInferenceResponse) {
            yield $partialInferenceResponse;
        }
    }

    /**
     * Retrieves all partial LLM responses from the given stream.
     *
     * @return PartialInferenceResponse[] An array of all partial LLM responses.
     */
    public function all() : array {
        return $this->getAllPartialInferenceResponses($this->stream);
    }

    /**
     * Returns the last partial response for the stream.
     * It will contain accumulated content and finish reason.
     *
     * @return ?InferenceResponse
     */
    public function final() : ?InferenceResponse {
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
     * @param Generator<PartialInferenceResponse> $stream A generator yielding raw partial LLM response strings.
     * @return InferenceResponse|null The final InferenceResponse object or null if not available.
     */
    protected function getFinalResponse(Generator $stream) : ?InferenceResponse {
        if ($this->finalInferenceResponse === null) {
            foreach ($this->tryMakePartialInferenceResponses($stream) as $partialResponse) { $tmp = $partialResponse; }
        }
        return $this->finalInferenceResponse;
    }

    /**
     * Retrieves all partial LLM responses from the provided generator stream.
     *
     * @param Generator<string> $stream The generator stream producing raw partial LLM response strings.
     * @return PartialInferenceResponse[] An array containing all PartialInferenceResponse objects.
     */
    protected function getAllPartialInferenceResponses(Generator $stream) : array {
        if ($this->finalInferenceResponse === null) {
            foreach ($this->tryMakePartialInferenceResponses($stream) as $partialResponse) { $tmp = $partialResponse; }
        }
        return $this->inferenceResponses;
    }

    private function tryMakePartialInferenceResponses(Generator $stream) : Generator {
        try {
            yield from $this->makePartialInferenceResponses($stream);
        } catch (\Throwable $e) {
            $this->events->dispatch(new InferenceFailed([
                'exception' => $e,
                'statusCode' => $this->httpResponse->statusCode(),
                'headers' => $this->httpResponse->headers(),
                'body' => $this->httpResponse->body(),
            ]));
            throw $e;
        }
    }

    /**
     * Processes the given stream to generate partial LLM responses and enriches them with accumulated content and finish reason.
     *
     * @param Generator<string> $stream The stream to be processed to extract and enrich partial LLM responses.
     * @return Generator<PartialInferenceResponse> A generator yielding enriched PartialInferenceResponse objects.
     */
    private function makePartialInferenceResponses(Generator $stream) : Generator {
        $content = '';
        $reasoningContent = '';
        $finishReason = '';
        $this->inferenceResponses = [];
        $this->lastPartialInferenceResponse = null;

        foreach ($this->getEventStream($stream) as $streamEvent) {
            if ($streamEvent === null || $streamEvent === '') {
                continue;
            }
            $data = Json::decode($streamEvent, []);
            $partialResponse = $this->driver->fromStreamResponse($data);
            if ($partialResponse === null) {
                continue;
            }
            $this->inferenceResponses[] = $partialResponse;

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
            $this->events->dispatch(new PartialInferenceResponseCreated($enrichedResponse));

            $this->lastPartialInferenceResponse = $enrichedResponse;
            if ($this->onPartialResponse !== null) {
                ($this->onPartialResponse)($enrichedResponse);
            }
            yield $enrichedResponse;
        }
        $this->finalInferenceResponse = InferenceResponse::fromPartialResponses($this->inferenceResponses);
        $this->events->dispatch(new InferenceResponseCreated($this->finalInferenceResponse));
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