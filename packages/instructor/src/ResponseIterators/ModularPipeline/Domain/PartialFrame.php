<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain;

use Cognesy\Instructor\Extraction\Buffers\JsonBuffer;
use Cognesy\Instructor\Extraction\Contracts\CanBufferContent;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Enums\EmissionType;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Utils\Result\Result;

/**
 * Immutable frame of data flowing through the partial streaming pipeline.
 *
 * Each frame represents one transformation step, carrying:
 * - Source data (the raw partial response)
 * - Progressive transformation (buffer → object via Result monad)
 * - Emission decision (explicit enum, no boolean flags)
 * - Metadata (index, timestamps for observability)
 *
 * Design: Pure data carrier with no business logic.
 * All state is immutable, transformations return new instances.
 */
final readonly class PartialFrame
{
    public function __construct(
        // Source
        public PartialInferenceResponse $source,

        // Progressive transformation state
        public CanBufferContent $buffer,
        public Result $object,  // Result<mixed> - Success or Failure

        // Emission control
        public EmissionType $emissionType,

        // Metadata
        public FrameMetadata $metadata,
    ) {}

    // FACTORIES /////////////////////////////////////////////////////////////

    public static function fromResponse(
        PartialInferenceResponse $response,
        int $index = 0,
    ): self {
        return new self(
            source: $response,
            buffer: JsonBuffer::empty(),
            object: Result::success(null),
            emissionType: EmissionType::None,
            metadata: FrameMetadata::at($index),
        );
    }

    // ACCESSORS /////////////////////////////////////////////////////////////

    public function hasContent(): bool {
        return !$this->buffer->isEmpty();
    }

    public function hasObject(): bool {
        return $this->object->isSuccess() && $this->object->unwrap() !== null;
    }

    public function shouldEmit(): bool {
        return $this->emissionType->shouldEmit();
    }

    public function isError(): bool {
        return $this->object->isFailure();
    }

    // TRANSFORMATIONS ///////////////////////////////////////////////////////

    public function withBuffer(CanBufferContent $buffer): self {
        return new self(
            source: $this->source,
            buffer: $buffer,
            object: $this->object,
            emissionType: $this->emissionType,
            metadata: $this->metadata,
        );
    }

    public function withObject(Result $result): self {
        return new self(
            source: $this->source,
            buffer: $this->buffer,
            object: $result,
            emissionType: $this->emissionType,
            metadata: $this->metadata,
        );
    }

    public function withEmission(EmissionType $emission): self {
        return new self(
            source: $this->source,
            buffer: $this->buffer,
            object: $this->object,
            emissionType: $emission,
            metadata: $this->metadata,
        );
    }

    public function withMetadata(FrameMetadata $metadata): self {
        return new self(
            source: $this->source,
            buffer: $this->buffer,
            object: $this->object,
            emissionType: $this->emissionType,
            metadata: $metadata,
        );
    }

    // CONVERSION ////////////////////////////////////////////////////////////

    public function toPartialResponse(): PartialInferenceResponse {
        $value = $this->object->isSuccess() ? $this->object->unwrap() : null;

        // Use normalized content so downstream validation/extraction
        // can reliably find JSON during streaming.
        return $this->source
            ->withValue($value)
            ->withContent($this->buffer->normalized());
    }
}
