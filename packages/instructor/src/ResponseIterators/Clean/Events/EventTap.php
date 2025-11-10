<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Clean\Events;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Events\PartialsGenerator\ChunkReceived;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseReceived;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallCompleted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallStarted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallUpdated;
use Cognesy\Instructor\Events\Request\SequenceUpdated;
use Cognesy\Instructor\ResponseIterators\Clean\Aggregation\StreamAggregate;
use Cognesy\Instructor\ResponseIterators\Clean\Domain\PartialFrame;
use Cognesy\Instructor\ResponseIterators\Clean\Domain\SequenceTracker;
use Cognesy\Instructor\ResponseIterators\Clean\Domain\ToolCallTracker;
use Cognesy\Instructor\ResponseIterators\Clean\Enums\Emission;
use Cognesy\Stream\Contracts\Reducer;

/**
 * Single decorator that dispatches ALL domain events for partial streaming.
 *
 * Consolidates event logic from:
 * - EventDispatchingStream
 * - SequenceEmitter
 * - HandleToolCallSignalsReducer
 *
 * Design: Observes PartialFrame and emits events based on emission decisions.
 * Deterministic ordering, single source of truth.
 */
final class EventTap implements Reducer
{
    private ToolCallTracker $toolTracker;
    private SequenceTracker $sequenceTracker;

    public function __construct(
        private readonly Reducer $inner,
        private readonly CanHandleEvents $events,
        private readonly string $expectedToolName = '',
    ) {
        $this->toolTracker = ToolCallTracker::empty();
        $this->sequenceTracker = SequenceTracker::empty();
    }

    #[\Override]
    public function init(): mixed {
        $this->toolTracker = ToolCallTracker::empty();
        $this->sequenceTracker = SequenceTracker::empty();
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        // Handle PartialFrame (from pipeline)
        if ($reducible instanceof PartialFrame) {
            $this->dispatchFrameEvents($reducible);
        }

        return $this->inner->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        // Finalize tool calls
        if ($this->expectedToolName !== '' && $this->toolTracker->hasActive()) {
            $finalCall = $this->toolTracker->finalize();
            if ($finalCall !== null) {
                $this->events->dispatch(new StreamedToolCallCompleted([
                    'toolCall' => $finalCall->toArray(),
                ]));
            }
        }

        // Finalize sequence
        $finalUpdates = $this->sequenceTracker->finalize();
        foreach ($finalUpdates as $update) {
            $this->events->dispatch(new SequenceUpdated($update));
        }

        // Mark stream as finished
        if ($accumulator instanceof StreamAggregate) {
            $this->events->dispatch(new StreamedResponseReceived([
                'finalResponse' => $accumulator->toInferenceResponse(),
            ]));
        }

        return $this->inner->complete($accumulator);
    }

    // INTERNAL EVENT DISPATCH ///////////////////////////////////////////////

    private function dispatchFrameEvents(PartialFrame $frame): void {
        // Raw chunk received
        $this->events->dispatch(new ChunkReceived([
            'chunk' => $frame->source,
        ]));

        // Tool call tracking and events
        if ($this->expectedToolName !== '') {
            $this->handleToolCallEvents($frame);
        }

        // Emission events based on Emission case
        match ($frame->emission) {
            Emission::ObjectReady => $this->dispatchObjectReady($frame),
            Emission::FinishOnly => $this->dispatchFinishOnly($frame),
            Emission::DriverValue => $this->dispatchDriverValue($frame),
            Emission::None => null,
        };

        // Sequence tracking and events
        $this->handleSequenceEvents($frame);
    }

    private function handleToolCallEvents(PartialFrame $frame): void {
        $previous = $this->toolTracker;

        // Handle tool name signal
        if ($frame->source->toolName !== '') {
            // If we have an active call with args, finalize it first
            if ($this->toolTracker->hasActive() && !$this->toolTracker->argsBuffer->isEmpty()) {
                $completedCall = $this->toolTracker->finalize();
                if ($completedCall !== null) {
                    $this->events->dispatch(new StreamedToolCallCompleted([
                        'toolCall' => $completedCall->toArray(),
                    ]));
                }
                $this->toolTracker = $this->toolTracker->clear();
            }

            $this->toolTracker = $this->toolTracker->handleSignal($frame->source->toolName);
        } else {
            // Start if empty
            $this->toolTracker = $this->toolTracker->startIfEmpty($this->expectedToolName);

            // Append args if present
            $delta = $frame->source->toolArgs;
            if ($delta !== '') {
                $this->toolTracker = $this->toolTracker->appendArgs($delta);
            }
        }

        // Detect state transitions and emit events
        if (!$previous->hasActive() && $this->toolTracker->hasActive()) {
            // Started
            $call = $this->toolTracker->currentCall();
            if ($call !== null) {
                $this->events->dispatch(new StreamedToolCallStarted([
                    'toolCall' => $call->toArray(),
                ]));
            }
        } elseif ($previous->hasActive() && $this->toolTracker->hasActive()) {
            // Updated (args changed)
            if (!$this->toolTracker->argsBuffer->equals($previous->argsBuffer)) {
                $call = $this->toolTracker->currentCall();
                if ($call !== null) {
                    $this->events->dispatch(new StreamedToolCallUpdated([
                        'toolCall' => $call->toArray(),
                    ]));
                }
            }
        }
    }

    private function handleSequenceEvents(PartialFrame $frame): void {
        if (!$frame->hasObject()) {
            return;
        }

        $object = $frame->object->unwrap();
        if (!$object instanceof Sequenceable) {
            return;
        }

        // Update tracker
        $this->sequenceTracker = $this->sequenceTracker->update($object);

        // Get pending updates
        $pending = $this->sequenceTracker->pending();

        // Emit events for each pending update
        foreach ($pending as $update) {
            $this->events->dispatch(new SequenceUpdated($update));
        }

        // Advance tracker (confirm emitted)
        $this->sequenceTracker = $this->sequenceTracker->advance();
    }

    private function dispatchObjectReady(PartialFrame $frame): void {
        if (!$frame->hasObject()) {
            return;
        }

        // Emit the typed object directly for partial update handlers
        $this->events->dispatch(new PartialResponseGenerated(
            $frame->object->unwrap()
        ));
    }

    private function dispatchFinishOnly(PartialFrame $frame): void {
        // Just note that stream finished (handled in complete())
    }

    private function dispatchDriverValue(PartialFrame $frame): void {
        // Driver provided value directly - forward it
        $this->events->dispatch(new PartialResponseGenerated(
            $frame->object->unwrap()
        ));
    }
}
