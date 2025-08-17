<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Services;

use Cognesy\Doctor\Doctest\Data\DiscoveredFile;
use Cognesy\Doctor\Doctest\Data\FileDiscoveryResult;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Service for discovering files in directories based on patterns and extensions
 */
class FileDiscoveryService
{
    private Filesystem $filesystem;

    public function __construct(?Filesystem $filesystem = null) {
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    /**
     * Find all files with specified extensions in a directory and its subdirectories
     *
     * @param string $sourceDirectory The directory to search in
     * @param array $extensions Array of file extensions (e.g., ['md', 'mdx'])
     * @return FileDiscoveryResult Collection of discovered files with their relative paths
     * @throws InvalidArgumentException If directory doesn't exist or extensions are invalid
     * @throws RuntimeException If directory cannot be read
     */
    public function discoverFiles(string $sourceDirectory, array $extensions): FileDiscoveryResult {
        $this->validateSourceDirectory($sourceDirectory);
        $this->validateExtensions($extensions);

        try {
            $finder = new Finder();
            $finder
                ->files()
                ->in($sourceDirectory)
                ->name($this->buildNamePatterns($extensions))
                ->sortByName();

            $discoveredFiles = [];
            foreach ($finder as $file) {
                $absolutePath = $file->getRealPath();
                $relativePath = $this->getRelativePath($sourceDirectory, $absolutePath);

                $discoveredFiles[] = new DiscoveredFile(
                    absolutePath: $absolutePath,
                    relativePath: $relativePath,
                    extension: $file->getExtension(),
                );
            }

            return new FileDiscoveryResult($discoveredFiles, $sourceDirectory);

        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to discover files in directory '{$sourceDirectory}': {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Build file name patterns from extensions
     */
    private function buildNamePatterns(array $extensions): array {
        return array_map(fn($ext) => "*.{$ext}", $extensions);
    }

    /**
     * Get relative path from source directory to target file
     */
    private function getRelativePath(string $sourceDirectory, string $absolutePath): string {
        $sourceDirectory = rtrim(realpath($sourceDirectory), DIRECTORY_SEPARATOR);
        $relativePath = substr($absolutePath, strlen($sourceDirectory) + 1);

        return str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
    }

    /**
     * Validate that source directory exists and is readable
     */
    private function validateSourceDirectory(string $sourceDirectory): void {
        if (empty($sourceDirectory)) {
            throw new InvalidArgumentException('Source directory cannot be empty');
        }

        if (!$this->filesystem->exists($sourceDirectory)) {
            throw new InvalidArgumentException("Source directory does not exist: {$sourceDirectory}");
        }

        if (!is_dir($sourceDirectory)) {
            throw new InvalidArgumentException("Path is not a directory: {$sourceDirectory}");
        }

        if (!is_readable($sourceDirectory)) {
            throw new InvalidArgumentException("Source directory is not readable: {$sourceDirectory}");
        }
    }

    /**
     * Validate file extensions array
     */
    private function validateExtensions(array $extensions): void {
        if (empty($extensions)) {
            throw new InvalidArgumentException('At least one file extension must be specified');
        }

        foreach ($extensions as $extension) {
            if (!is_string($extension) || empty($extension)) {
                throw new InvalidArgumentException('File extensions must be non-empty strings');
            }

            if (str_contains($extension, '.')) {
                throw new InvalidArgumentException("File extension should not contain dots: {$extension}");
            }
        }
    }
}

