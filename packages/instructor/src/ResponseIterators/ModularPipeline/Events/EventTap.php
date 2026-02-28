<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Events;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Events\PartialsGenerator\ChunkReceived;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseReceived;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallCompleted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallStarted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallUpdated;
use Cognesy\Instructor\Events\Request\SequenceUpdated;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Aggregation\StreamAggregate;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain\PartialFrame;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain\SequenceTracker;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Enums\EmissionType;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
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
    private string $activeToolKey;
    private ToolCalls $lastToolCalls;
    private SequenceTracker $sequenceTracker;

    public function __construct(
        private readonly Reducer $inner,
        private readonly CanHandleEvents $events,
        private readonly string $expectedToolName = '',
    ) {
        $this->activeToolKey = '';
        $this->lastToolCalls = ToolCalls::empty();
        $this->sequenceTracker = SequenceTracker::empty();
    }

    #[\Override]
    public function init(): mixed {
        $this->activeToolKey = '';
        $this->lastToolCalls = ToolCalls::empty();
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
        if ($this->expectedToolName !== '' && $this->hasActiveTool()) {
            $this->emitToolCompletedForActive($this->lastToolCalls);
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
        match ($frame->emissionType) {
            EmissionType::ObjectReady, EmissionType::DriverValue => $this->dispatchPartialResponse($frame),
            EmissionType::None => null,
        };

        // Sequence tracking and events
        $this->handleSequenceEvents($frame);
    }

    private function handleToolCallEvents(PartialFrame $frame): void {
        $source = $frame->source;
        $toolCalls = $source->toolCalls();

        $signaledKey = $this->toolSignalKey($source);
        if ($signaledKey !== '') {
            $this->transitionToolStart($signaledKey, $toolCalls);
        }

        if ($source->toolArgs !== '') {
            $this->transitionToolUpdate($toolCalls);
        }

        $this->lastToolCalls = $toolCalls;
    }

    private function transitionToolStart(string $toolKey, ToolCalls $toolCalls): void {
        if ($this->hasActiveTool() && !$this->isActiveToolKey($toolKey)) {
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

    private function toolSignalKey(PartialInferenceResponse $source): string {
        if ($source->toolId !== '') {
            return 'id:' . $source->toolId;
        }

        if ($source->toolName !== '') {
            return 'name:' . $source->toolName;
        }

        return '';
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

    private function dispatchPartialResponse(PartialFrame $frame): void {
        if (!$frame->hasObject()) {
            return;
        }

        $this->events->dispatch(new PartialResponseGenerated(
            $frame->object->unwrap()
        ));
    }
}
