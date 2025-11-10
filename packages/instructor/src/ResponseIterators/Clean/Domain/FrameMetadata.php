<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Clean\Domain;

/**
 * Metadata about a pipeline frame for observability and debugging.
 */
final readonly class FrameMetadata
{
    public function __construct(
        public int $index,
        public float $timestamp,
    ) {}

    public static function at(int $index): self {
        return new self($index, microtime(true));
    }

    public function next(): self {
        return new self($this->index + 1, microtime(true));
    }
}
