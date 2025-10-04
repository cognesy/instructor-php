<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming;

use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallCompleted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallStarted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallUpdated;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Psr\EventDispatcher\EventDispatcherInterface;

final class ToolCallStreamState
{
    public function __construct(
        private EventDispatcherInterface $events,
        private ToolCalls $toolCalls = new ToolCalls(),
        private bool $requiresBufferReset = false,
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

    // MUTATORS ////////////////////////////////////////////////

    public function startIfEmpty(string $name): self {
        if (!$this->isEmpty()) {
            return new self($this->events, $this->toolCalls, false);
        }
        return $this->start($name);
    }

    public function update(string $name, string $responseJson): self {
        $next = $this->toolCalls->withLastToolCallUpdated($name, $responseJson);
        $newPartialToolCalls = new self($this->events, $next, false);
        $newPartialToolCalls->dispatchUpdated($next->last());
        return $newPartialToolCalls;
    }

    public function finalize(string $name, string $responseJson): self {
        $next = $this->toolCalls->withLastToolCallUpdated($name, $responseJson);
        $newPartialToolCalls = new self($this->events, $next, false);
        $newPartialToolCalls->dispatchCompleted($next->last());
        return $newPartialToolCalls;
    }

    public function handleSignal(string $signaledName, ?string $bufferedJson, string $defaultToolName): self {
        $active = $this->toolCalls->last();
        $duplicateStart = ($active !== null) && ($bufferedJson === null) && ($active->name() === $signaledName);
        if ($duplicateStart) {
            return new self($this->events, $this->toolCalls, false);
        }

        $newPartialToolCalls = $this;
        $requiresReset = false;
        if ($bufferedJson !== null && $this->toolCalls->count() > 0) {
            $newPartialToolCalls = $newPartialToolCalls->finalize($defaultToolName, $bufferedJson);
            $requiresReset = true;
        }

        $newPartialToolCalls = $newPartialToolCalls->start($signaledName);
        return new self($this->events, $newPartialToolCalls->toolCalls(), $requiresReset);
    }

    // INTERNAL ////////////////////////////////////////////////////

    private function start(string $name): self {
        $next = $this->toolCalls->withAddedToolCall($name);
        $newPartialToolCalls = new self($this->events, $next, false);
        $newPartialToolCalls->dispatchStarted($next->last());
        return $newPartialToolCalls;
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
