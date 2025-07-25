<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Services;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Repository for reading and writing documentation files
 */
class DocRepository
{
    private Filesystem $filesystem;

    public function __construct(?Filesystem $filesystem = null) {
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    /**
     * Read content from a file
     *
     * @throws InvalidArgumentException If file is not accessible
     * @throws FileNotFoundException If file does not exist
     * @throws RuntimeException If file cannot be read
     */
    public function readFile(string $filePath): string {
        $this->validateFileForReading($filePath);

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Failed to read file: {$filePath}");
        }

        return $content;
    }

    /**
     * Write content to a file
     *
     * @throws RuntimeException If file cannot be written
     */
    public function writeFile(string $filePath, string $content): void {
        try {
            // Ensure output directory exists
            $outputDir = dirname($filePath);
            $this->ensureDirectoryExists($outputDir);

            // Write content atomically
            $tempFile = $filePath . '.tmp';
            if (file_put_contents($tempFile, $content) === false) {
                throw new RuntimeException("Failed to write temporary file: {$tempFile}");
            }

            if (!rename($tempFile, $filePath)) {
                @unlink($tempFile); // Clean up temp file
                throw new RuntimeException("Failed to move temporary file to final location: {$filePath}");
            }

        } catch (\Exception $e) {
            throw new RuntimeException("Failed to write file: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Ensure a directory exists, creating it if necessary
     *
     * @throws RuntimeException If directory cannot be created
     */
    public function ensureDirectoryExists(string $directoryPath): void {
        if ($this->filesystem->exists($directoryPath)) {
            return;
        }

        try {
            $this->filesystem->mkdir($directoryPath, 0755);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to create directory: {$directoryPath}. {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Check if a file exists
     */
    public function fileExists(string $filePath): bool {
        return $this->filesystem->exists($filePath);
    }

    /**
     * Check if a file is readable
     */
    public function isReadable(string $filePath): bool {
        return is_readable($filePath);
    }

    /**
     * Check if a directory is writable
     */
    public function isDirectoryWritable(string $dirPath): bool {
        if (!$this->filesystem->exists($dirPath)) {
            // Check if parent directory is writable for creating new directories
            $parentDir = dirname($dirPath);
            if ($parentDir === $dirPath) {
                return false; // Reached root directory
            }
            return $this->isDirectoryWritable($parentDir);
        }
        return is_writable($dirPath);
    }

    /**
     * Get file size in bytes
     *
     * @throws RuntimeException If file size cannot be determined
     */
    public function getFileSize(string $filePath): int {
        $this->validateFileForReading($filePath);

        $size = filesize($filePath);
        if ($size === false) {
            throw new RuntimeException("Cannot determine file size: {$filePath}");
        }

        return $size;
    }

    /**
     * Get file modification time
     *
     * @throws RuntimeException If modification time cannot be determined
     */
    public function getFileModificationTime(string $filePath): int {
        $this->validateFileForReading($filePath);

        $mtime = filemtime($filePath);
        if ($mtime === false) {
            throw new RuntimeException("Cannot determine file modification time: {$filePath}");
        }

        return $mtime;
    }

    /**
     * Create a backup of a file
     *
     * @throws RuntimeException If backup cannot be created
     */
    public function createBackup(string $filePath, ?string $backupSuffix = null): string {
        $this->validateFileForReading($filePath);

        $backupSuffix = $backupSuffix ?? date('Y-m-d_H-i-s');
        $backupPath = $filePath . '.backup.' . $backupSuffix;

        try {
            $this->filesystem->copy($filePath, $backupPath);
            return $backupPath;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to create backup: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Validate that a file can be read
     *
     * @throws InvalidArgumentException If file path is invalid
     * @throws FileNotFoundException If file does not exist
     */
    private function validateFileForReading(string $filePath): void {
        if (empty($filePath)) {
            throw new InvalidArgumentException('File path cannot be empty');
        }

        if (!$this->filesystem->exists($filePath)) {
            throw new FileNotFoundException("File not found: {$filePath}");
        }

        if (!$this->isReadable($filePath)) {
            throw new InvalidArgumentException("File is not readable: {$filePath}");
        }
    }
}