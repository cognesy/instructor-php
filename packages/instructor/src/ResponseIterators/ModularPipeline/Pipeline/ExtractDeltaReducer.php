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
 * Builds per-frame extraction buffers from cumulative PartialInferenceResponse snapshots.
 *
 * Chooses buffer type based on OutputMode (Tools, JSON, Text, etc.).
 * Buffer responsibility is normalization/parsing only - no cross-frame assembly.
 *
 * Converts raw PartialInferenceResponse into PartialFrame.
 */
final class ExtractDeltaReducer implements Reducer
{
    private int $frameIndex = 0;

    /**
     * @param Closure(OutputMode): CanBufferContent|null $bufferFactory Optional factory for content buffer
     */
    public function __construct(
        private readonly Reducer $inner,
        private readonly OutputMode $mode,
        private readonly ?Closure $bufferFactory = null,
    ) {}

    #[\Override]
    public function init(): mixed {
        $this->frameIndex = 0;
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialInferenceResponse);

        // Snapshot is the source of truth - reducer no longer assembles deltas.
        $snapshot = $this->snapshotContent($reducible);

        // Skip empty snapshots without finish/value
        if ($this->shouldSkip($reducible, $snapshot)) {
            return $accumulator;
        }

        $buffer = $this->bufferFromSnapshot($snapshot);

        // Create frame from response with per-chunk snapshot buffer
        $frame = $this->createFrame($reducible, $this->frameIndex++, $buffer);

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

    private function snapshotContent(PartialInferenceResponse $response): string {
        return match ($this->mode) {
            OutputMode::Tools => $this->toolsSnapshotContent($response),
            default => $response->content(),
        };
    }

    private function toolsSnapshotContent(PartialInferenceResponse $response): string {
        $toolCalls = $response->toolCalls();
        if ($toolCalls->isEmpty()) {
            return '';
        }

        try {
            return match (true) {
                $toolCalls->hasSingle() => json_encode($toolCalls->first()?->args() ?? [], JSON_THROW_ON_ERROR),
                default => json_encode($toolCalls->toArray(), JSON_THROW_ON_ERROR),
            };
        } catch (\JsonException) {
            return '';
        }
    }

    private function bufferFromSnapshot(string $snapshot): CanBufferContent {
        if ($snapshot === '') {
            return $this->createEmptyBuffer();
        }

        return $this->createEmptyBuffer()->assemble($snapshot);
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

    private function shouldSkip(PartialInferenceResponse $reducible, string $snapshot): bool {
        return $snapshot === ''
            && $reducible->finishReason() === ''
            && !$reducible->hasValue();
    }
}
