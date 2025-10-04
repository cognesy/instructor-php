<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming;

use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallCompleted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallStarted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallUpdated;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Json\Json;
use Psr\EventDispatcher\EventDispatcherInterface;

final class ToolCallStreamState
{
    public function __construct(
        private EventDispatcherInterface $events,
        private ToolCalls $toolCalls = new ToolCalls(),
        private bool $requiresBufferReset = false,
        private string $activeName = '',
        private string $rawArgs = '',
    ) {}

    public static function empty(EventDispatcherInterface $events): self {
        return new self($events, ToolCalls::empty(), false);
    }

    // ACCESSORS ///////////////////////////////////////////////

    public function toolCalls(): ToolCalls {
        return $this->toolCalls;
    }

    public function lastToolCall() : ?ToolCall {
        return $this->toolCalls->last();
    }

    public function isEmpty(): bool {
        return $this->toolCalls->isEmpty();
    }

    public function requiresBufferReset(): bool {
        return $this->requiresBufferReset;
    }

    public function hasActive(): bool {
        return $this->activeName !== '';
    }

    public function activeName(): string {
        return $this->activeName;
    }

    public function rawArgs(): string {
        return $this->rawArgs;
    }

    public function normalizedArgs(): string {
        if ($this->rawArgs === '') {
            return '';
        }
        return Json::fromPartial($this->rawArgs)->toString();
    }

    public function finalizedArgs(): string {
        if ($this->rawArgs === '') {
            return '';
        }
        return Json::fromString($this->rawArgs)->toString();
    }

    // MUTATORS ////////////////////////////////////////////////

    public function startIfEmpty(string $name): self {
        if ($this->hasActive()) {
            return new self($this->events, $this->toolCalls, false, $this->activeName, $this->rawArgs);
        }
        return $this->start($name);
    }

    // Append partial arguments delta to the active tool call and emit an update event
    public function appendArgsDelta(string $delta): self {
        if (trim($delta) === '') {
            return new self($this->events, $this->toolCalls, false, $this->activeName, $this->rawArgs);
        }
        $raw = $this->rawArgs . $delta;
        $normalized = Json::fromPartial($raw)->toString();
        $newState = new self($this->events, $this->toolCalls, false, $this->activeName, $raw);
        if ($normalized !== '') {
            $toolCall = new ToolCall($this->activeName, Json::fromString($normalized)->toArray());
            $newState->dispatchUpdated($toolCall);
        }
        return $newState;
    }

    // Finalize the active tool call (if any) and emit a completion event
    public function finalizeActive(): self {
        if (!$this->hasActive()) {
            return new self($this->events, $this->toolCalls, false, $this->activeName, $this->rawArgs);
        }
        $finalized = $this->rawArgs === '' ? [] : Json::fromString($this->rawArgs)->toArray();
        $toolCall = new ToolCall($this->activeName, $finalized);
        $newState = new self($this->events, $this->toolCalls, false, '', '');
        $newState->dispatchCompleted($toolCall);
        return $newState;
    }

    // Handle a new tool signal: finalize current (if args present) and start a new active tool
    public function handleSignal(string $signaledName): self {
        // If duplicate start without any args yet, ignore
        if ($this->hasActive() && $this->rawArgs === '' && $this->activeName === $signaledName) {
            return new self($this->events, $this->toolCalls, false, $this->activeName, $this->rawArgs);
        }
        $state = $this;
        if ($state->hasActive() && $state->rawArgs !== '') {
            $state = $state->finalizeActive();
        }
        return $state->start($signaledName);
    }

    // INTERNAL ////////////////////////////////////////////////////

    private function start(string $name): self {
        $toolCall = new ToolCall($name, []);
        $newState = new self($this->events, $this->toolCalls, false, $name, '');
        $newState->dispatchStarted($toolCall);
        return $newState;
    }

    private function dispatchStarted(ToolCall $toolCall): void {
        $this->events->dispatch(new StreamedToolCallStarted(['toolCall' => $toolCall?->toArray()]));
    }

    private function dispatchUpdated(ToolCall $toolCall): void {
        $this->events->dispatch(new StreamedToolCallUpdated(['toolCall' => $toolCall?->toArray()]));
    }

    private function dispatchCompleted(ToolCall $toolCall): void {
        $this->events->dispatch(new StreamedToolCallCompleted(['toolCall' => $toolCall?->toArray()]));
    }
}
