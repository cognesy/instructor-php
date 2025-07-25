<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Services;

use Cognesy\Doctor\Doctest\Data\BatchProcessingResult;
use Cognesy\Doctor\Doctest\Data\DiscoveredFile;
use Cognesy\Doctor\Doctest\Data\FileDiscoveryResult;
use Cognesy\Doctor\Doctest\Data\FileProcessingResult;
use Cognesy\Doctor\Markdown\MarkdownFile;
use RuntimeException;

/**
 * Service for batch processing of multiple markdown files
 */
class BatchProcessingService
{
    public function __construct(
        private DocRepository $docRepository,
    ) {}

    /**
     * Process multiple files and write them to target directory with preserved structure
     *
     * @param FileDiscoveryResult $discoveryResult The files to process
     * @param string $targetDirectory The directory to write processed files to
     * @return BatchProcessingResult The result of the batch operation
     * @throws RuntimeException If processing fails
     */
    public function processFiles(FileDiscoveryResult $discoveryResult, string $targetDirectory): BatchProcessingResult
    {
        $this->validateTargetDirectory($targetDirectory);

        $results = [];
        $totalSnippetsProcessed = 0;

        foreach ($discoveryResult->getFiles() as $file) {
            try {
                $result = $this->processFile($file, $targetDirectory);
                $results[] = $result;
                $totalSnippetsProcessed += $result->snippetsProcessed;

            } catch (\Exception $e) {
                $results[] = new FileProcessingResult(
                    file: $file,
                    success: false,
                    snippetsProcessed: 0,
                    error: $e->getMessage()
                );
            }
        }

        return new BatchProcessingResult(
            results: $results,
            totalSnippetsProcessed: $totalSnippetsProcessed
        );
    }

    /**
     * Process a single file
     */
    private function processFile(DiscoveredFile $file, string $targetDirectory): FileProcessingResult
    {
        // Read source content
        $sourceContent = $this->docRepository->readFile($file->absolutePath);

        // Process content
        $markdown = MarkdownFile::fromString($sourceContent, $file->absolutePath);
        $snippetsProcessed = iterator_count($markdown->codeBlocks());

        // Ensure target directory exists
        $targetDir = $file->getTargetDirectory($targetDirectory);
        $this->docRepository->ensureDirectoryExists($targetDir);

        // Write processed content
        $targetPath = $file->getTargetPath($targetDirectory);
        $this->docRepository->writeFile($targetPath, $markdown->toString());

        return new FileProcessingResult(
            file: $file,
            success: true,
            snippetsProcessed: $snippetsProcessed,
            targetPath: $targetPath
        );
    }

    /**
     * Validate target directory can be used for writing
     */
    private function validateTargetDirectory(string $targetDirectory): void
    {
        if (empty($targetDirectory)) {
            throw new RuntimeException('Target directory cannot be empty');
        }

        // Check if parent directory is writable (for creating new directories)
        $parentDir = dirname($targetDirectory);
        if (!$this->docRepository->isDirectoryWritable($parentDir)) {
            throw new RuntimeException("Cannot write to target directory: {$targetDirectory}");
        }
    }
}

