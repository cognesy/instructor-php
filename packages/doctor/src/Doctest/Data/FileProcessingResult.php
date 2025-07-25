<?php

namespace Cognesy\Doctor\Doctest\Data;

/**
 * Result of processing a single file
 */
class FileProcessingResult
{
    public function __construct(
        public readonly DiscoveredFile $file,
        public readonly bool $success,
        public readonly int $snippetsProcessed,
        public readonly ?string $error = null,
        public readonly ?string $targetPath = null,
    ) {}

    /**
     * Check if this file processing was successful
     */
    public function isSuccess(): bool {
        return $this->success;
    }

    /**
     * Check if this file processing failed
     */
    public function isFailure(): bool {
        return !$this->success;
    }

    /**
     * Get the relative path of the source file
     */
    public function getSourceRelativePath(): string {
        return $this->file->relativePath;
    }
}