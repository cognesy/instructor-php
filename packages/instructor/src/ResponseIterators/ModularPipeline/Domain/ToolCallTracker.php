<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain;

use Cognesy\Instructor\ResponseIterators\ModularPipeline\ContentBuffer\ContentBuffer;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\ContentBuffer\JsonBuffer;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Json\Json;

/**
 * Tracks active tool call state during streaming.
 *
 * Accumulates tool call arguments as they arrive in chunks.
 * Does NOT handle event emission (that's in EventTap).
 *
 * Replaces ToolCallStreamState without embedded closures.
 * All state is pure data, no behavior.
 */
final readonly class ToolCallTracker
{
    private function __construct(
        public string $activeName,
        public ContentBuffer $argsBuffer,
    ) {}

    public static function empty(): self {
        return new self(
            activeName: '',
            argsBuffer: JsonBuffer::empty(),
        );
    }

    public function hasActive(): bool {
        return $this->activeName !== '';
    }

    /**
     * Handle tool name signal from stream.
     *
     * If signal matches active name, continue.
     * If different and we have active call, finalize first.
     * Then start new call.
     */
    public function handleSignal(string $signaledName): self {
        // Same tool already active - continue
        if ($this->hasActive() && $this->activeName === $signaledName && $this->argsBuffer->isEmpty()) {
            return $this;
        }

        // Start fresh (any previous call should be finalized by caller)
        return $this->start($signaledName);
    }

    /**
     * Start call if no active call, otherwise keep current.
     */
    public function startIfEmpty(string $name): self {
        if ($this->hasActive()) {
            return $this;
        }

        return $this->start($name);
    }

    /**
     * Append args delta to current buffer.
     */
    public function appendArgs(string $delta): self {
        if (!$this->hasActive()) {
            return $this;
        }

        return new self(
            activeName: $this->activeName,
            argsBuffer: $this->argsBuffer->assemble($delta),
        );
    }

    /**
     * Get current tool call (may be incomplete).
     */
    public function currentCall(): ?ToolCall {
        if (!$this->hasActive()) {
            return null;
        }

        $args = $this->argsBuffer->isEmpty()
            ? []
            : Json::fromString($this->argsBuffer->normalized())->toArray();

        return new ToolCall($this->activeName, $args);
    }

    /**
     * Finalize current call and return it.
     */
    public function finalize(): ?ToolCall {
        return $this->currentCall();
    }

    /**
     * Clear active call state.
     */
    public function clear(): self {
        return self::empty();
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    private function start(string $name): self {
        return new self(
            activeName: $name,
            argsBuffer: JsonBuffer::empty(),
        );
    }
}
