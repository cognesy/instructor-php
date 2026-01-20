<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Pipeline;

use Closure;
use Cognesy\Instructor\Extraction\Buffers\ExtractingBuffer;
use Cognesy\Instructor\Extraction\Contracts\CanBufferContent;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain\FrameMetadata;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain\PartialFrame;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Enums\EmissionType;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;

/**
 * Extracts content delta from PartialInferenceResponse and accumulates it into a buffer.
 *
 * Chooses buffer type based on OutputMode (Tools, JSON, Text, etc.).
 * Buffer accumulates all deltas across chunks - single source of truth.
 *
 * Converts raw PartialInferenceResponse into PartialFrame.
 */
final class ExtractDeltaReducer implements Reducer
{
    private int $frameIndex = 0;
    private CanBufferContent $accumulatedBuffer;

    /**
     * @param Closure(OutputMode): CanBufferContent|null $bufferFactory Optional factory for content buffer
     */
    public function __construct(
        private readonly Reducer $inner,
        private readonly OutputMode $mode,
        private readonly ?Closure $bufferFactory = null,
    ) {
        $this->accumulatedBuffer = $this->createEmptyBuffer();
    }

    #[\Override]
    public function init(): mixed {
        $this->frameIndex = 0;
        $this->accumulatedBuffer = $this->createEmptyBuffer();
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialInferenceResponse);

        // Extract delta based on mode
        $delta = match ($this->mode) {
            OutputMode::Tools => $reducible->toolArgs ?: $reducible->contentDelta,
            default => $reducible->contentDelta,
        };

        // Skip empty deltas without finish/value
        if ($this->shouldSkip($reducible, $delta)) {
            return $accumulator;
        }

        // Always accumulate delta into persistent buffer across chunks
        // Buffer is single source of truth for accumulated content
        if ($delta !== '') {
            $this->accumulatedBuffer = $this->accumulatedBuffer->assemble($delta);
        }

        // Create frame from response with accumulated buffer
        $frame = $this->createFrame($reducible, $this->frameIndex++, $this->accumulatedBuffer);

        return $this->inner->step($accumulator, $frame);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    private function createEmptyBuffer(): CanBufferContent {
        // Use custom buffer factory if provided
        if ($this->bufferFactory !== null) {
            return ($this->bufferFactory)($this->mode);
        }

        // Default buffer selection
        return ExtractingBuffer::empty($this->mode);
    }

    private function createFrame(
        PartialInferenceResponse $response,
        int $index,
        CanBufferContent $buffer
    ): PartialFrame {
        return new PartialFrame(
            source: $response,
            buffer: $buffer,
            object: Result::success(null),
            emissionType: EmissionType::None,
            metadata: FrameMetadata::at($index),
        );
    }

    private function shouldSkip(PartialInferenceResponse $reducible, string $delta): bool {
        return $delta === ''
            && $reducible->finishReason() === ''
            && !$reducible->hasValue();
    }
}
