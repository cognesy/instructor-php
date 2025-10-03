<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming;

use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallCompleted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallStarted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallUpdated;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Psr\EventDispatcher\EventDispatcherInterface;

final class ToolCallAssembler
{
    public function __construct(
        private EventDispatcherInterface $events,
        private ToolCalls $toolCalls = new ToolCalls(),
    ) {}

    public static function empty(EventDispatcherInterface $events): self {
        return new self($events, ToolCalls::empty());
    }

    public function toolCalls(): ToolCalls {
        return $this->toolCalls;
    }

    public function isEmpty(): bool {
        return $this->toolCalls->isEmpty();
    }

    public function startIfEmpty(string $name): self {
        if (!$this->isEmpty()) {
            return $this;
        }
        return $this->start($name);
    }

    public function start(string $name): self {
        $next = $this->toolCalls->withAddedToolCall($name);
        $assembler = new self($this->events, $next);
        $assembler->dispatchStarted();
        return $assembler;
    }

    public function update(string $name, string $responseJson): self {
        $next = $this->toolCalls->withLastToolCallUpdated($name, $responseJson);
        $assembler = new self($this->events, $next);
        $assembler->dispatchUpdated();
        return $assembler;
    }

    public function finalize(string $name, string $responseJson): self {
        $next = $this->toolCalls->withLastToolCallUpdated($name, $responseJson);
        $assembler = new self($this->events, $next);
        $assembler->dispatchCompleted();
        return $assembler;
    }

    public function handleSignal(string $signaledName, ?string $bufferedJson, string $defaultToolName): ToolCallAssemblerResult {
        $active = $this->toolCalls->last();
        $duplicateStart = ($active !== null) && ($bufferedJson === null) && ($active->name() === $signaledName);
        if ($duplicateStart) {
            return new ToolCallAssemblerResult($this, false);
        }

        $assembler = $this;
        $requiresReset = false;
        if ($bufferedJson !== null && $this->toolCalls->count() > 0) {
            $assembler = $assembler->finalize($defaultToolName, $bufferedJson);
            $requiresReset = true;
        }

        $assembler = $assembler->start($signaledName);
        return new ToolCallAssemblerResult($assembler, $requiresReset);
    }

    private function dispatchStarted(): void {
        $newToolCall = $this->toolCalls->last();
        $this->events->dispatch(new StreamedToolCallStarted(['toolCall' => $newToolCall?->toArray()]));
    }

    private function dispatchUpdated(): void {
        $updated = $this->toolCalls->last();
        $this->events->dispatch(new StreamedToolCallUpdated(['toolCall' => $updated?->toArray()]));
    }

    private function dispatchCompleted(): void {
        $final = $this->toolCalls->last();
        $this->events->dispatch(new StreamedToolCallCompleted(['toolCall' => $final?->toArray()]));
    }
}

