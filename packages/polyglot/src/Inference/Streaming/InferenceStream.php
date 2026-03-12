<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Streaming;

use ArrayIterator;
use Closure;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Events\PartialInferenceDeltaCreated;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\Events\StreamFirstChunkReceived;
use DateTimeImmutable;
use Generator;
use Iterator;
use IteratorIterator;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Traversable;

/**
 * The InferenceStream class is responsible for handling and processing streamed responses
 * from language models in a structured and event-driven manner. It allows for real-time
 * processing of incoming data and supports partial and cumulative responses.
 */
class InferenceStream
{
    protected readonly EventDispatcherInterface $events;
    protected readonly CanProcessInferenceRequest $driver;
    /** @var (Closure(PartialInferenceDelta): void)|null */
    protected ?Closure $onDelta = null;

    /** @var Iterator<int, PartialInferenceDelta> */
    private Iterator $deltaStream;
    private bool $deltaStreamInitialized = false;
    private InferenceStreamState $state;
    private VisibilityTracker $visibility;
    private ?PartialInferenceDelta $lastDelta = null;

    protected InferenceExecution $execution;
    private bool $streamConsumed = false;

    private ?DateTimeImmutable $startedAt;
    private bool $firstChunkReceived = false;
    /** @var (Closure(InferenceResponse): InferenceResponse)|null */
    private ?Closure $decorateFinalResponse = null;

    /**
     * @param (Closure(InferenceResponse):InferenceResponse)|null $decorateFinalResponse
     */
    public function __construct(
        InferenceExecution         $execution,
        CanProcessInferenceRequest $driver,
        EventDispatcherInterface   $eventDispatcher,
        ?DateTimeImmutable         $startedAt = null,
        ?Closure                   $decorateFinalResponse = null,
    ) {
        $this->execution = $execution;
        $this->driver = $driver;
        $this->events = $eventDispatcher;
        $this->startedAt = $startedAt ?? new DateTimeImmutable();
        $this->deltaStream = $this->toIterator($driver->makeStreamDeltasFor($execution->request()));
        $this->state = new InferenceStreamState();
        $this->visibility = new VisibilityTracker();
        $this->decorateFinalResponse = $decorateFinalResponse;
    }

    /**
     * Generates and yields visible inference deltas from the given stream.
     *
     * @return Generator<PartialInferenceDelta>
     */
    public function deltas(): Generator {
        if ($this->streamConsumed) {
            throw new LogicException(
                'Stream is exhausted and cannot be replayed.'
            );
        }

        foreach ($this->makeDeltas() as $delta) {
            yield $delta;
        }
        $this->streamConsumed = true;
    }

    /**
     * @template T
     * @param callable(PartialInferenceDelta):T $mapper
     * @return iterable<T>
     */
    public function map(callable $mapper): iterable {
        foreach ($this->deltas() as $delta) {
            yield $mapper($delta);
        }
    }

    /**
     * @template T
     * @param callable(T, PartialInferenceDelta):T $reducer
     * @param mixed|null $initial
     * @return T
     */
    public function reduce(callable $reducer, mixed $initial = null): mixed {
        $carry = $initial;
        foreach ($this->deltas() as $delta) {
            $carry = $reducer($carry, $delta);
        }
        return $carry;
    }

    /**
     * @param callable(PartialInferenceDelta):bool $filter
     * @return iterable<PartialInferenceDelta>
     */
    public function filter(callable $filter): iterable {
        foreach ($this->deltas() as $delta) {
            if ($filter($delta)) {
                yield $delta;
            }
        }
    }

    /**
     * Retrieves all visible deltas from the given stream.
     *
     * @return array<PartialInferenceDelta>
     */
    public function all(): array {
        $deltas = [];
        foreach ($this->deltas() as $delta) {
            $deltas[] = $delta;
        }
        return $deltas;
    }

    /**
     * Returns the finalized response assembled from stream state.
     */
    public function final(): ?InferenceResponse {
        if ($this->execution->response() === null && !$this->execution->isFinalized()) {
            // Drain the stream to ensure all deltas are processed and the final
            // response + events are produced even if the caller stopped early.
            foreach ($this->deltas() as $_) {}
        }
        return $this->execution->response();
    }

    /**
     * Sets a callback to be called when a visible delta is received.
     *
     * @param callable(PartialInferenceDelta): void $callback
     */
    public function onDelta(callable $callback): self {
        $this->onDelta = $callback(...);
        return $this;
    }

    public function execution(): InferenceExecution {
        return $this->execution;
    }

    public function lastDelta(): ?PartialInferenceDelta {
        return $this->lastDelta;
    }

    public function usage(): InferenceUsage {
        return $this->state->usage();
    }

    // INTERNAL //////////////////////////////////////////////

    /**
     * @return Generator<PartialInferenceDelta>
     */
    private function makeDeltas(): Generator {
        yield from $this->emitVisibleDeltas();
    }

    /**
     * @return Generator<PartialInferenceDelta>
     */
    private function emitVisibleDeltas(): Generator {
        $this->initializeDeltaStream();

        while ($this->deltaStream->valid()) {
            $delta = $this->deltaStream->current();
            assert($delta instanceof PartialInferenceDelta);

            $visibleDelta = $this->advanceState($delta);
            $this->deltaStream->next();
            if ($visibleDelta === null) {
                continue;
            }

            if (!$this->firstChunkReceived) {
                $this->dispatchFirstChunkReceived($visibleDelta);
                $this->firstChunkReceived = true;
            }

            $this->notifyOnDelta($visibleDelta);
            yield $visibleDelta;
        }

        $this->finalizeDeltaStream();
    }

    private function initializeDeltaStream(): void {
        if ($this->deltaStreamInitialized) {
            return;
        }

        $this->deltaStream->rewind();
        $this->deltaStreamInitialized = true;
    }

    /**
     * Dispatches the first chunk received event for TTFC measurement.
     */
    private function dispatchFirstChunkReceived(PartialInferenceDelta $delta): void {
        $now = new DateTimeImmutable();
        $startedAt = $this->startedAt ?? $now;

        $this->events->dispatch(new StreamFirstChunkReceived(
            executionId: $this->execution->id->toString(),
            requestStartedAt: $startedAt,
            model: $this->execution->request()->model(),
            initialContent: $delta->contentDelta,
        ));
    }

    /**
     * Dispatches events and calls callback for the visible delta.
     */
    private function notifyOnDelta(PartialInferenceDelta $delta): void {
        $this->events->dispatch(new PartialInferenceDeltaCreated(
            executionId: $this->execution->id->toString(),
            partialInferenceDelta: $delta,
        ));

        if ($this->onDelta !== null) {
            ($this->onDelta)($delta);
        }
    }

    private function finalizeDeltaStream(): void {
        if ($this->execution->isFinalized()) {
            return;
        }

        $response = $this->state->finalResponse();

        if ($this->decorateFinalResponse !== null) {
            $response = ($this->decorateFinalResponse)($response);
        }

        $this->execution = match (true) {
            $response->hasFinishedWithFailure() => $this->execution->withFailedAttempt(
                response: $response,
                usage: $response->usage(),
            ),
            default => $this->execution->withSuccessfulAttempt($response),
        };

        $this->events->dispatch(new InferenceResponseCreated($response));
    }

    private function advanceState(PartialInferenceDelta $delta): ?PartialInferenceDelta
    {
        $this->state->applyDelta($delta);
        if (!$this->visibility->hasVisibleChange($this->state)) {
            return null;
        }

        $this->visibility->remember($this->state);
        $this->lastDelta = $delta;
        return $this->lastDelta;
    }

    /**
     * @param iterable<PartialInferenceDelta> $deltaStream
     * @return Iterator<int, PartialInferenceDelta>
     */
    private function toIterator(iterable $deltaStream): Iterator {
        return match (true) {
            is_array($deltaStream) => new ArrayIterator($deltaStream),
            $deltaStream instanceof Iterator => $deltaStream,
            $deltaStream instanceof Traversable => new IteratorIterator($deltaStream),
            default => new ArrayIterator(),
        };
    }

    /**
     * @deprecated Use deltas() instead.
     *
     * @return Generator<PartialInferenceDelta>
     */
    public function responses(): Generator {
        yield from $this->deltas();
    }

    /**
     * @deprecated Use onDelta() instead.
     *
     * @param callable(PartialInferenceDelta): void $callback
     */
    public function onPartialResponse(callable $callback): self {
        return $this->onDelta($callback);
    }

    /**
     * @deprecated Use lastDelta() instead.
     */
    public function partialResponse(): ?PartialInferenceDelta {
        return $this->lastDelta();
    }
}
