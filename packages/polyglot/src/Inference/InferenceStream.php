<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Closure;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\Events\PartialInferenceResponseCreated;
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
    protected InferenceRequest $request;
    protected CanHandleInference $driver;
    /** @var iterable<PartialInferenceResponse> */
    protected iterable $stream;

    protected bool $streamReceived = false;
    protected array $inferenceResponses = [];
    protected ?InferenceResponse $finalInferenceResponse = null;
    protected ?PartialInferenceResponse $lastPartialInferenceResponse = null;
    protected ?Closure $onPartialResponse = null;

    public function __construct(
        InferenceRequest         $request,
        CanHandleInference       $driver,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->request = $request;
        $this->driver = $driver;
        $this->events = $eventDispatcher;
        $this->stream = $driver->makeStreamResponsesFor($request);
    }

    /**
     * Generates and yields partial LLM responses from the given stream.
     *
     * @return Generator<PartialInferenceResponse> A generator yielding partial LLM responses.
     */
    public function responses() : Generator {
        foreach ($this->makePartialResponses($this->stream) as $partialInferenceResponse) {
            yield $partialInferenceResponse;
        }
    }

    /**
     * Retrieves all partial LLM responses from the given stream.
     *
     * @return iterable<PartialInferenceResponse> An array of all partial LLM responses.
     */
    public function all() : array {
        if ($this->finalInferenceResponse === null) {
            foreach ($this->makePartialResponses($this->stream) as $partialResponse) { $tmp = $partialResponse; }
        }
        return $this->inferenceResponses;
    }

    /**
     * Returns the last partial response for the stream.
     * It will contain accumulated content and finish reason.
     *
     * @return ?InferenceResponse
     */
    public function final() : ?InferenceResponse {
        if ($this->finalInferenceResponse === null) {
            foreach ($this->makePartialResponses($this->stream) as $partialResponse) { $tmp = $partialResponse; }
        }
        return $this->finalInferenceResponse;
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
     * Processes the given stream to generate partial LLM responses and enriches them with accumulated content and finish reason.
     *
     * @param Generator<PartialInferenceResponse> $stream The stream to be processed to extract and enrich partial LLM responses.
     * @return Generator<PartialInferenceResponse> A generator yielding enriched PartialInferenceResponse objects.
     */
     private function makePartialResponses(iterable $stream) : Generator {
        $content = '';
        $reasoningContent = '';
        $finishReason = '';
        $this->inferenceResponses = [];
        $this->lastPartialInferenceResponse = null;

        foreach ($stream as $partialResponse) {
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
        $this->streamReceived = true;
        $this->finalInferenceResponse = InferenceResponse::fromPartialResponses($this->inferenceResponses);
        $this->events->dispatch(new InferenceResponseCreated($this->finalInferenceResponse));
    }
}