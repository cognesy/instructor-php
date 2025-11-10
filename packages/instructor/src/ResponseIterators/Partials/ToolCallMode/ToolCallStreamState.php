<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Partials\ToolCallMode;

use Closure;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Json\Json;

final class ToolCallStreamState
{
    private ToolCalls $toolCalls;
    private bool $requiresBufferReset;
    private string $activeName;
    private string $rawArgs;

    /** @var Closure(ToolCall): void */
    private Closure $onStart;
    /** @var Closure(ToolCall): void */
    private Closure $onUpdate;
    /** @var Closure(ToolCall): void */
    private Closure $onComplete;

    /**
     * @param Closure(ToolCall): void|null $onStart
     * @param Closure(ToolCall): void|null $onUpdate
     * @param Closure(ToolCall): void|null $onComplete
     */
    public function __construct(
        ?ToolCalls $toolCalls = null,
        ?bool $requiresBufferReset = null,
        ?string $activeName = null,
        ?string $rawArgs = null,
        ?Closure $onStart = null,
        ?Closure $onUpdate = null,
        ?Closure $onComplete = null,
    ) {
        $this->toolCalls = $toolCalls ?? ToolCalls::empty();
        $this->requiresBufferReset = $requiresBufferReset ?? false;
        $this->activeName = $activeName ?? '';
        $this->rawArgs = $rawArgs ?? '';
        $this->onStart = $onStart ?? fn(ToolCall $tc) => null;
        $this->onUpdate = $onUpdate ?? fn(ToolCall $tc) => null;
        $this->onComplete = $onComplete ?? fn(ToolCall $tc) => null;
    }

    /**
     * @param Closure(ToolCall): void|null $onStart
     * @param Closure(ToolCall): void|null $onUpdate
     * @param Closure(ToolCall): void|null $onComplete
     */
    public static function empty(
        ?Closure $onStart = null,
        ?Closure $onUpdate = null,
        ?Closure $onComplete = null,
    ): self {
        return new self(
            toolCalls: ToolCalls::empty(),
            requiresBufferReset: false,
            activeName: '',
            rawArgs: '',
            onStart: $onStart,
            onUpdate: $onUpdate,
            onComplete: $onComplete,
        );
    }

    // ACCESSORS /////////////////////////////////////////////////

    public function hasActive(): bool {
        return $this->activeName !== '';
    }

    // MUTATORS //////////////////////////////////////////////////

    public function startIfEmpty(string $name): self {
        if ($this->hasActive()) {
            return $this->with(toolCalls: $this->toolCalls, requiresBufferReset: false, activeName: $this->activeName, rawArgs: $this->rawArgs);
        }
        return $this->start($name);
    }

    public function finalizeActive(): self {
        if (!$this->hasActive()) {
            return $this->with(toolCalls: $this->toolCalls, requiresBufferReset: false, activeName: $this->activeName, rawArgs: $this->rawArgs);
        }
        $finalized = $this->rawArgs === '' ? [] : Json::fromString($this->rawArgs)->toArray();
        $toolCall = new ToolCall($this->activeName, $finalized);
        $newState = $this->with(toolCalls: $this->toolCalls, requiresBufferReset: false, activeName: '', rawArgs: '');
        ($newState->onComplete)($toolCall);
        return $newState;
    }

    public function handleSignal(string $signaledName): self {
        if ($this->hasActive() && $this->rawArgs === '' && $this->activeName === $signaledName) {
            return $this->with(toolCalls: $this->toolCalls, requiresBufferReset: false, activeName: $this->activeName, rawArgs: $this->rawArgs);
        }
        $state = $this;
        if ($state->hasActive() && $state->rawArgs !== '') {
            $state = $state->finalizeActive();
        }
        return $state->start($signaledName);
    }

    // INTERNAL //////////////////////////////////////////////////

    public function with(
        ?ToolCalls $toolCalls = null,
        ?bool $requiresBufferReset = null,
        ?string $activeName = null,
        ?string $rawArgs = null,
    ): self {
        return new self(
            toolCalls: $toolCalls ?? $this->toolCalls,
            requiresBufferReset: $requiresBufferReset ?? $this->requiresBufferReset,
            activeName: $activeName ?? $this->activeName,
            rawArgs: $rawArgs ?? $this->rawArgs,
            onStart: $this->onStart,
            onUpdate: $this->onUpdate,
            onComplete: $this->onComplete,
        );
    }

    private function start(string $name): self {
        $toolCall = new ToolCall($name, []);
        $newState = $this->with(toolCalls: $this->toolCalls, requiresBufferReset: false, activeName: $name, rawArgs: '');
        ($newState->onStart)($toolCall);
        return $newState;
    }
}
