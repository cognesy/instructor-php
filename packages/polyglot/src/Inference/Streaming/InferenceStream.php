<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Streaming;

use Closure;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
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
    protected readonly EventDispatcherInterface $events;
    protected readonly CanHandleInference $driver;
    /** @var (Closure(PartialInferenceResponse): void)|null */
    protected ?Closure $onPartialResponse = null;

    /** @var iterable<PartialInferenceResponse> */
    protected iterable $stream;

    protected InferenceExecution $execution;

    public function __construct(
        InferenceExecution $execution,
        CanHandleInference $driver,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->execution = $execution;
        $this->driver = $driver;
        $this->events = $eventDispatcher;
        $this->stream = $driver->makeStreamResponsesFor($execution->request());
    }

    /**
     * Generates and yields partial LLM responses from the given stream.
     *
     * @return Generator<PartialInferenceResponse> A generator yielding partial LLM responses.
     */
    public function responses(): Generator {
        foreach ($this->makePartialResponses($this->stream) as $partialInferenceResponse) {
            yield $partialInferenceResponse;
        }
    }

    /**
     * @template T
     * @param callable(PartialInferenceResponse):T $mapper
     * @return iterable<T>
     */
    public function map(callable $mapper): iterable {
        foreach ($this->responses() as $partialInferenceResponse) {
            yield $mapper($partialInferenceResponse);
        }
    }

    /**
     * @template T
     * @param callable(T, PartialInferenceResponse):T $reducer
     * @param mixed|null $initial
     * @return T
     */
    public function reduce(callable $reducer, mixed $initial = null): mixed {
        $carry = $initial;
        foreach ($this->responses() as $partialInferenceResponse) {
            $carry = $reducer($carry, $partialInferenceResponse);
        }
        return $carry;
    }

    /**
     * @param callable(PartialInferenceResponse):bool $filter
     * @return iterable<PartialInferenceResponse>
     */
    public function filter(callable $filter): iterable {
        foreach ($this->responses() as $partialInferenceResponse) {
            if ($filter($partialInferenceResponse)) {
                yield $partialInferenceResponse;
            }
        }
    }

    /**
     * Retrieves all partial LLM responses from the given stream.
     *
     * @return array<PartialInferenceResponse> An array of all partial LLM responses.
     */
    public function all(): array {
        $responses = [];
        if ($this->execution->response() === null) {
            foreach ($this->makePartialResponses($this->stream) as $partialResponse) {
                $responses[] = $partialResponse;
            }
        }
        return $responses;
    }

    /**
     * Returns the last partial response for the stream.
     * It will contain accumulated content and finish reason.
     *
     * @return ?InferenceResponse
     */
    public function final(): ?InferenceResponse {
        if ($this->execution->response() === null) {
            if ($this->execution->partialResponse()) {
                $this->execution = $this->execution->withFinalizedPartialResponse();
            } else {
                foreach ($this->makePartialResponses($this->stream) as $_) {}
            }
        }
        return $this->execution->response();
    }

    /**
     * Sets a callback to be called when a partial response is received.
     *
     * @param callable(PartialInferenceResponse): void $callback
     */
    public function onPartialResponse(callable $callback): self {
        $this->onPartialResponse = $callback(...);
        return $this;
    }

    public function execution(): InferenceExecution {
        return $this->execution;
    }

    // INTERNAL //////////////////////////////////////////////

    /**
     * Processes the given stream to generate partial LLM responses and enriches them with accumulated content and finish reason.
     *
     * @param iterable<PartialInferenceResponse> $stream The stream to be processed to extract and enrich partial LLM responses.
     * @return Generator<PartialInferenceResponse> A generator yielding enriched PartialInferenceResponse objects.
     */
    private function makePartialResponses(iterable $stream): Generator {
        $priorResponse = PartialInferenceResponse::empty();
        /** @var PartialInferenceResponse $partialResponse */
        foreach ($stream as $partialResponse) {
            if ($partialResponse === null) {
                continue;
            }
            // Always enrich with accumulated content/state (tools, usage, content)
            $partialResponse = $partialResponse->withAccumulatedContent($priorResponse);
            $this->notifyOnPartialResponse($partialResponse);
            yield $partialResponse;
            // we need this to accumulate some fields (content, finish reason, reasoning content)
            $priorResponse = $partialResponse;
        }

        $this->finalizeStream();
    }

    /**
     * Dispatches events and calls callback for partial response.
     */
    private function notifyOnPartialResponse(PartialInferenceResponse $enrichedResponse): void {
        $this->events->dispatch(new PartialInferenceResponseCreated($enrichedResponse));
        $this->execution = $this->execution->withNewPartialResponse($enrichedResponse);

        if ($this->onPartialResponse !== null) {
            ($this->onPartialResponse)($enrichedResponse);
        }
    }

    /**
     * Finalizes the stream by creating final response and dispatching event.
     */
    private function finalizeStream(): void {
        $this->execution = $this->execution->withFinalizedPartialResponse();
        $this->events->dispatch(new InferenceResponseCreated($this->execution->response()));
    }
}
