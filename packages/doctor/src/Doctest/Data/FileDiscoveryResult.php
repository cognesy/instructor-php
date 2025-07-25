<?php

namespace Cognesy\Doctor\Doctest\Data;

/**
 * Result of file discovery operation
 */
class FileDiscoveryResult
{
    /**
     * @param \Cognesy\InstructorHub\Services\TestDocs\FileDiscovery\DiscoveredFile[] $files
     */
    public function __construct(
        private readonly array $files,
        private readonly string $sourceDirectory,
    ) {}

    /**
     * Get all discovered files
     *
     * @return \Cognesy\InstructorHub\Services\TestDocs\FileDiscovery\DiscoveredFile[]
     */
    public function getFiles(): array {
        return $this->files;
    }

    /**
     * Get the number of discovered files
     */
    public function getCount(): int {
        return count($this->files);
    }

    /**
     * Get the source directory that was scanned
     */
    public function getSourceDirectory(): string {
        return $this->sourceDirectory;
    }

    /**
     * Check if any files were found
     */
    public function hasFiles(): bool {
        return !empty($this->files);
    }

    /**
     * Get files grouped by extension
     *
     * @return DiscoveredFile
     */
    public function getFilesByExtension(): array {
        $grouped = [];
        foreach ($this->files as $file) {
            $grouped[$file->extension][] = $file;
        }
        return $grouped;
    }
}