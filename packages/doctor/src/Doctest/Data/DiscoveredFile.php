<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Data;

/**
 * Represents a single discovered file
 */
class DiscoveredFile
{
    public function __construct(
        public readonly string $absolutePath,
        public readonly string $relativePath,
        public readonly string $extension,
    ) {}

    /**
     * Get the target path for this file in the output directory
     */
    public function getTargetPath(string $outputDirectory): string {
        return rtrim($outputDirectory, '/\\') . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $this->relativePath);
    }

    /**
     * Get the directory that should contain this file in the target
     */
    public function getTargetDirectory(string $outputDirectory): string {
        return dirname($this->getTargetPath($outputDirectory));
    }
}