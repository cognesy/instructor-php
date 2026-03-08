<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming\Pipeline;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Events\PartialsGenerator\ChunkReceived;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseReceived;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallCompleted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallStarted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallUpdated;
use Cognesy\Instructor\Events\Request\SequenceUpdated;
use Cognesy\Instructor\Streaming\EmissionSnapshot;
use Cognesy\Instructor\Streaming\StructuredOutputStreamState;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Stream\Contracts\Reducer;

/**
 * Single decorator that dispatches ALL domain events for partial streaming.
 */
final class DispatchStreamingEventsReducer implements Reducer
{
    private string $activeToolKey;
    private ToolCalls $lastToolCalls;
    private ?StructuredOutputStreamState $lastState;
    private int $previousSequenceLength;
    private ?Sequenceable $currentSequence;

    public function __construct(
        private readonly Reducer $inner,
        private readonly CanHandleEvents $events,
        private readonly string $expectedToolName = '',
    ) {
        $this->activeToolKey = '';
        $this->lastToolCalls = ToolCalls::empty();
        $this->lastState = null;
        $this->previousSequenceLength = 0;
        $this->currentSequence = null;
    }

    #[\Override]
    public function init(): mixed {
        $this->activeToolKey = '';
        $this->lastToolCalls = ToolCalls::empty();
        $this->lastState = null;
        $this->previousSequenceLength = 0;
        $this->currentSequence = null;
        return $this->inner->init();
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
        if ($this->expectedToolName !== '' && $this->hasActiveTool()) {
            $this->emitToolCompletedForActive($this->lastToolCalls);
        }

        // Finalize sequence
        if ($this->currentSequence !== null && count($this->currentSequence) > 0) {
            $this->events->dispatch(new SequenceUpdated($this->currentSequence));
        }

        $this->events->dispatch(new StreamedResponseReceived([
            'finalResponse' => $this->finalResponse(),
        ]));

        return $this->inner->complete($accumulator);
    }

    // INTERNAL EVENT DISPATCH ///////////////////////////////////////////////

    private function dispatchPartialEvents(StructuredOutputStreamState $state): void {
        $snapshot = EmissionSnapshot::fromState($state);

        $this->events->dispatch(new ChunkReceived([
            'chunk' => $state,
        ]));

        if ($this->expectedToolName !== '') {
            $this->handleToolCallEventsFromState($state, $snapshot);
        }

        $this->dispatchPartialResponseFromState($state);
        $this->handleSequenceEventsForSnapshot($snapshot);
        $this->lastState = $state;
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
        if ($this->lastState === null) {
            return InferenceResponse::empty();
        }

        return $this->lastState->finalRawResponse();
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
