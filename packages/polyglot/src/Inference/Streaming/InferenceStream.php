<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Streaming;

use ArrayIterator;
use Closure;
use Cognesy\Events\Support\ListenerGate;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Core\InferenceResponseEventPayload;
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

    /**
     * Whether anything consumes the per-delta event; see EventStreamReader for the
     * cost rationale. Resolved once per stream, so listeners registered mid-stream
     * are not picked up.
     */
    private readonly bool $emitDeltaCreated;
    /**
     * Whether anything consumes the final response event. Once per stream, so the
     * saving is nil — it is guarded because this was the last unguarded
     * InferenceResponseCreated site in the package, sitting on the one class that
     * already had a guard field. See ListenerGate.
     */
    private readonly bool $emitResponseCreated;
    /** Memoized execution id — stringifying it per delta showed up on the hot path. */
    private ?string $executionIdString = null;

    private DateTimeImmutable $startedAt;
    private bool $firstChunkReceived = false;
    /** @var (Closure(InferenceResponse): InferenceResponse)|null */
    private ?Closure $decorateFinalResponse = null;
    /** @var (Closure(InferenceExecution): void)|null */
    private ?Closure $onFinalizedExecution = null;
    /** @var (Closure(\Throwable, InferenceUsage): void)|null */
    private ?Closure $onStreamFailed = null;

    /**
     * @param (Closure(InferenceResponse):InferenceResponse)|null $decorateFinalResponse
     * @param (Closure(InferenceExecution):void)|null $onFinalizedExecution
     * @param (Closure(\Throwable, InferenceUsage):void)|null $onStreamFailed
     */
    public function __construct(
        InferenceExecution         $execution,
        CanProcessInferenceRequest $driver,
        EventDispatcherInterface   $eventDispatcher,
        ?DateTimeImmutable         $startedAt = null,
        ?Closure                   $decorateFinalResponse = null,
        ?Closure                   $onFinalizedExecution = null,
        ?Closure                   $onStreamFailed = null,
    ) {
        $this->execution = $execution;
        $this->driver = $driver;
        $this->events = $eventDispatcher;
        $this->startedAt = $startedAt ?? new DateTimeImmutable();
        $this->deltaStream = $this->toIterator($driver->makeStreamDeltasFor($execution->request()));
        $this->state = new InferenceStreamState();
        $this->visibility = new VisibilityTracker();
        $this->decorateFinalResponse = $decorateFinalResponse;
        $this->onFinalizedExecution = $onFinalizedExecution;
        $this->onStreamFailed = $onStreamFailed;
        $this->emitDeltaCreated = ListenerGate::wants($eventDispatcher, PartialInferenceDeltaCreated::class);
        $this->emitResponseCreated = ListenerGate::wants($eventDispatcher, InferenceResponseCreated::class);
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

        // Iterate emitVisibleDeltas() directly — a pass-through makeDeltas()
        // generator used to sit in between, costing one frame resumption per
        // delta for no behaviour. Keep the `foreach`/`yield` form rather than
        // `yield from`: it pins key renumbering at this boundary regardless of
        // what the inner generator does. (`yield from` would be equivalent today,
        // since emitVisibleDeltas() yields with auto-keys, but it would silently
        // start propagating explicit keys if that ever changed — and callers do
        // iterator_to_array($stream->deltas()) with keys preserved.)
        foreach ($this->emitVisibleDeltas() as $delta) {
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
    public function onDelta(callable $callback): InferenceStream {
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
    private function emitVisibleDeltas(): Generator {
        try {
            // INSIDE the try, and this is the whole point. initializeDeltaStream() calls
            // rewind(), and rewind() is what first advances the driver's generator -- so it
            // is where a connection dropped at handshake surfaces. It used to sit above the
            // try, which meant the most likely streaming failure of all was the one failure
            // onStreamFailed never saw: no InferenceAttemptFailed, no InferenceCompleted, no
            // terminal error recorded, while a failure one delta later reported all three.
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
        } catch (\Throwable $error) {
            if ($this->onStreamFailed !== null) {
                ($this->onStreamFailed)($error, $this->state->usage());
            }

            throw $error;
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
        $startedAt = $this->startedAt;

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
        if ($this->emitDeltaCreated) {
            $this->events->dispatch(new PartialInferenceDeltaCreated([
                'executionId' => $this->executionIdString ??= $this->execution->id->toString(),
                'contentDelta' => $delta->contentDelta,
            ]));
        }

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

        if ($this->emitResponseCreated) {
            $this->events->dispatch(new InferenceResponseCreated(
                InferenceResponseEventPayload::build(
                    $response,
                    $this->execution->request(),
                    $this->executionIdString ??= $this->execution->id->toString(),
                ),
            ));
        }

        if ($this->onFinalizedExecution !== null) {
            ($this->onFinalizedExecution)($this->execution);
        }
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
    public function onPartialResponse(callable $callback): InferenceStream {
        return $this->onDelta($callback);
    }

    /**
     * @deprecated Use lastDelta() instead.
     */
    public function partialResponse(): ?PartialInferenceDelta {
        return $this->lastDelta();
    }
}
