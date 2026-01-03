<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Contracts;

use Cognesy\Utils\Result\Result;

/**
 * Buffer abstraction for assembling streaming content.
 *
 * Enables different content modes (JSON, text, binary) with uniform interface.
 * Implementations handle mode-specific assembly and normalization logic.
 */
interface CanBufferContent
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
     * Get parsed structured content.
     *
     * @return Result<array<string, mixed>, string>
     */
    public function parsed(): Result;

    /**
     * Check if buffer is empty.
     */
    public function isEmpty(): bool;
}
