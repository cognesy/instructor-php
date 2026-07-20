<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming\Pipeline;

use Cognesy\Events\Contracts\CanCheckListeners;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Events\Streaming\ChunkReceived;
use Cognesy\Instructor\Events\Streaming\PartialResponseGenerated;
use Cognesy\Instructor\Events\Streaming\StreamedResponseReceived;
use Cognesy\Instructor\Events\Streaming\StreamedToolCallCompleted;
use Cognesy\Instructor\Events\Streaming\StreamedToolCallStarted;
use Cognesy\Instructor\Events\Streaming\StreamedToolCallUpdated;
use Cognesy\Instructor\Events\Streaming\SequenceUpdated;
use Cognesy\Instructor\Streaming\EmissionSnapshot;
use Cognesy\Instructor\Streaming\StructuredOutputStreamState;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Messages\ToolCall;
use Cognesy\Stream\Contracts\Reducer;

/**
 * Single decorator that dispatches ALL domain events for partial streaming.
 *
 * When the dispatcher supports listener introspection (CanCheckListeners),
 * event families nobody listens to are skipped entirely — including the
 * payload construction that would otherwise run on every delta. Gates are
 * resolved once per stream (at init); listeners registered mid-stream are
 * not picked up until the next attempt.
 */
final class DispatchStreamingEventsReducer implements Reducer
{
    private string $activeToolKey;
    private ToolCalls $lastToolCalls;
    private ?InferenceResponse $lastInferenceResponse;
    private int $previousSequenceLength;
    private ?Sequenceable $currentSequence;

    private bool $emitChunkEvents = true;
    private bool $emitToolEvents = true;
    private bool $emitPartialResponseEvents = true;
    private bool $emitSequenceEvents = true;
    private bool $emitFinalResponseEvent = true;
    private bool $hasPartialEventListeners = true;

    public function __construct(
        private readonly Reducer $inner,
        private readonly CanHandleEvents $events,
        private readonly string $expectedToolName = '',
    ) {
        $this->activeToolKey = '';
        $this->lastToolCalls = ToolCalls::empty();
        $this->lastInferenceResponse = null;
        $this->previousSequenceLength = 0;
        $this->currentSequence = null;
    }

    #[\Override]
    public function init(): mixed {
        $this->activeToolKey = '';
        $this->lastToolCalls = ToolCalls::empty();
        $this->lastInferenceResponse = null;
        $this->previousSequenceLength = 0;
        $this->currentSequence = null;
        $this->resolveEventGates();
        return $this->inner->init();
    }

    private function resolveEventGates(): void {
        $this->emitChunkEvents = $this->hasListenersFor(ChunkReceived::class);
        $this->emitToolEvents = $this->hasListenersFor(StreamedToolCallStarted::class)
            || $this->hasListenersFor(StreamedToolCallUpdated::class)
            || $this->hasListenersFor(StreamedToolCallCompleted::class);
        $this->emitPartialResponseEvents = $this->hasListenersFor(PartialResponseGenerated::class);
        $this->emitSequenceEvents = $this->hasListenersFor(SequenceUpdated::class);
        $this->emitFinalResponseEvent = $this->hasListenersFor(StreamedResponseReceived::class);
        $this->hasPartialEventListeners = $this->emitChunkEvents
            || $this->emitToolEvents
            || $this->emitPartialResponseEvents
            || $this->emitSequenceEvents;
    }

    /** @param class-string $eventClass */
    private function hasListenersFor(string $eventClass): bool {
        return !($this->events instanceof CanCheckListeners)
            || $this->events->hasListenersFor($eventClass);
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if ($reducible instanceof StructuredOutputStreamState) {
            $this->dispatchPartialEvents($reducible);
        }

        return $this->inner->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        // Finalize tool calls
        if ($this->emitToolEvents && $this->expectedToolName !== '' && $this->hasActiveTool()) {
            $this->emitToolCompletedForActive($this->lastToolCalls);
        }

        // Finalize sequence
        if ($this->emitSequenceEvents && $this->currentSequence !== null && count($this->currentSequence) > 0) {
            $this->events->dispatch(new SequenceUpdated($this->currentSequence));
        }

        if ($this->emitFinalResponseEvent) {
            $this->events->dispatch(new StreamedResponseReceived([
                'finalResponse' => $this->finalResponse(),
            ]));
        }

        return $this->inner->complete($accumulator);
    }

    // INTERNAL EVENT DISPATCH ///////////////////////////////////////////////

    private function dispatchPartialEvents(StructuredOutputStreamState $state): void {
        if ($this->hasPartialEventListeners) {
            $snapshot = $state->snapshot();

            if ($this->emitChunkEvents) {
                $this->events->dispatch(new ChunkReceived([
                    'contentLength' => strlen($state->content()),
                    'hasValue' => $state->hasValue(),
                    'finishReason' => $state->finishReason(),
                    'snapshotRevision' => $state->snapshotRevision(),
                ]));
            }

            if ($this->emitToolEvents && $this->expectedToolName !== '') {
                $this->handleToolCallEventsFromState($state, $snapshot);
            }

            if ($this->emitPartialResponseEvents) {
                $this->dispatchPartialResponseFromState($state);
            }
            if ($this->emitSequenceEvents) {
                $this->handleSequenceEventsForSnapshot($snapshot);
            }
        }

        if ($this->emitFinalResponseEvent) {
            // Capture immutable response now rather than keeping a mutable state reference
            $this->lastInferenceResponse = $state->finalInferenceResponse();
        }
    }

    private function handleToolCallEventsFromState(
        StructuredOutputStreamState $state,
        EmissionSnapshot $snapshot,
    ): void {
        $toolCalls = $state->toolCalls();
        $signaledKey = $snapshot->toolKey;
        if ($signaledKey !== '') {
            $this->transitionToolStart($signaledKey, $toolCalls);
        }

        if ($snapshot->toolArgsSnapshot !== '') {
            $this->transitionToolUpdate($toolCalls);
        }

        $this->lastToolCalls = $toolCalls;
    }

    private function transitionToolStart(string $toolKey, ToolCalls $toolCalls): void {
        if ($this->isActiveToolKey($toolKey)) {
            return;
        }

        if ($this->hasActiveTool()) {
            $this->emitToolCompletedForActive($toolCalls);
        }

        $this->activateToolKey($toolKey);
        $this->emitToolStarted($toolKey, $toolCalls);
    }

    private function transitionToolUpdate(ToolCalls $toolCalls): void {
        $activeToolKey = $this->activeToolKey;
        if ($activeToolKey === '') {
            $activeToolKey = $this->fallbackToolKey($toolCalls);
            if ($activeToolKey === '') {
                return;
            }
            $this->activateToolKey($activeToolKey);
        }

        $call = $this->findCallByKey($toolCalls, $activeToolKey);
        if ($call === null) {
            return;
        }

        $this->events->dispatch(new StreamedToolCallUpdated([
            'toolCall' => $call->toArray(),
        ]));
    }

    private function emitToolStarted(string $toolKey, ToolCalls $toolCalls): void {
        $call = $this->findCallByKey($toolCalls, $toolKey);
        if ($call === null) {
            return;
        }

        $this->events->dispatch(new StreamedToolCallStarted([
            'toolCall' => $call->toArray(),
        ]));
    }

    private function emitToolCompletedForActive(ToolCalls $toolCalls): void {
        if (!$this->hasActiveTool()) {
            return;
        }

        $activeToolKey = $this->activeToolKey;
        $call = $this->findCallByKey($toolCalls, $activeToolKey)
            ?? $this->findCallByKey($this->lastToolCalls, $activeToolKey);
        if ($call === null) {
            return;
        }

        $this->events->dispatch(new StreamedToolCallCompleted([
            'toolCall' => $call->toArray(),
        ]));
    }

    private function fallbackToolKey(ToolCalls $toolCalls): string {
        if ($this->expectedToolName !== '') {
            $byName = $this->findLatestCallByName($toolCalls, $this->expectedToolName);
            if ($byName !== null) {
                return $this->toolKeyFromCall($byName);
            }
        }

        $latest = $toolCalls->last();
        return match (true) {
            $latest !== null => $this->toolKeyFromCall($latest),
            default => '',
        };
    }

    private function findCallByKey(ToolCalls $toolCalls, string $toolKey): ?ToolCall {
        if (str_starts_with($toolKey, 'id:')) {
            $id = substr($toolKey, 3);
            foreach ($toolCalls->all() as $call) {
                if ((string) ($call->id() ?? '') === $id) {
                    return $call;
                }
            }
            return null;
        }

        if (str_starts_with($toolKey, 'name:')) {
            $name = substr($toolKey, 5);
            return $this->findLatestCallByName($toolCalls, $name);
        }

        return null;
    }

    private function findLatestCallByName(ToolCalls $toolCalls, string $name): ?ToolCall {
        $matched = null;
        foreach ($toolCalls->all() as $call) {
            if ($call->name() === $name) {
                $matched = $call;
            }
        }
        return $matched;
    }

    private function toolKeyFromCall(ToolCall $call): string {
        $id = (string) ($call->id() ?? '');
        if ($id !== '') {
            return 'id:' . $id;
        }

        return 'name:' . $call->name();
    }

    private function hasActiveTool(): bool {
        return $this->activeToolKey !== '';
    }

    private function isActiveToolKey(string $toolKey): bool {
        return $this->activeToolKey === $toolKey && $this->activeToolKey !== '';
    }

    private function activateToolKey(string $toolKey): void {
        if ($toolKey === '') {
            return;
        }

        $this->activeToolKey = $toolKey;
    }

    private function handleSequenceEventsForSnapshot(EmissionSnapshot $snapshot): void {
        if (!$snapshot->hasValue()) {
            return;
        }

        $this->handleSequenceEventsForObject($snapshot->value);
    }

    private function dispatchPartialResponseFromState(StructuredOutputStreamState $state): void {
        if (!$state->hasValue()) {
            return;
        }

        $this->events->dispatch(new PartialResponseGenerated(
            $state->value()
        ));
    }

    private function finalResponse(): InferenceResponse {
        return $this->lastInferenceResponse ?? InferenceResponse::empty();
    }

    private function handleSequenceEventsForObject(mixed $object): void {
        if (!$object instanceof Sequenceable) {
            return;
        }

        $currentLength = count($object);

        // Emit events for each item that completed (a new item appeared after it).
        // No cloning — pass the original sequence with the index of the completed item.
        for ($i = $this->previousSequenceLength; $i < $currentLength - 1; $i++) {
            $this->events->dispatch(new SequenceUpdated($object, $i));
        }

        $this->previousSequenceLength = max(0, $currentLength - 1);
        $this->currentSequence = $object;
    }
}
