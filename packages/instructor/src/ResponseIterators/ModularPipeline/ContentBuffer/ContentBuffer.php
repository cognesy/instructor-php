<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\ContentBuffer;

/**
 * Buffer abstraction for assembling streaming content.
 *
 * Enables different content modes (JSON, text, binary) with uniform interface.
 * Implementations handle mode-specific assembly and normalization logic.
 */
interface ContentBuffer
{
    /**
     * Append a delta to the buffer and return new buffer instance.
     */
    public function assemble(string $delta): self;

    /**
     * Get raw accumulated content.
     */
    public function raw(): string;

    /**
     * Get normalized content (e.g., completed JSON, trimmed text).
     */
    public function normalized(): string;

    /**
     * Check if buffer is empty.
     */
    public function isEmpty(): bool;
}
