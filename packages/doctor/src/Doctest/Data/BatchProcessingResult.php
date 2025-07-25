<?php

namespace Cognesy\Doctor\Doctest\Data;

/**
 * Result of batch processing operation
 */
class BatchProcessingResult
{
    /**
     * @param FileProcessingResult[] $results
     */
    public function __construct(
        private readonly array $results,
        public readonly int $totalSnippetsProcessed,
    ) {}

    /**
     * Get all file processing results
     *
     * @return FileProcessingResult[]
     */
    public function getResults(): array {
        return $this->results;
    }

    /**
     * Get only successful processing results
     *
     * @return FileProcessingResult[]
     */
    public function getSuccessfulResults(): array {
        return array_filter($this->results, fn($result) => $result->isSuccess());
    }

    /**
     * Get only failed processing results
     *
     * @return FileProcessingResult[]
     */
    public function getFailedResults(): array {
        return array_filter($this->results, fn($result) => $result->isFailure());
    }

    /**
     * Get the total number of files processed
     */
    public function getTotalFilesProcessed(): int {
        return count($this->results);
    }

    /**
     * Get the number of successfully processed files
     */
    public function getSuccessfulFilesCount(): int {
        return count($this->getSuccessfulResults());
    }

    /**
     * Get the number of failed files
     */
    public function getFailedFilesCount(): int {
        return count($this->getFailedResults());
    }

    /**
     * Check if all files were processed successfully
     */
    public function isCompletelySuccessful(): bool {
        return $this->getFailedFilesCount() === 0;
    }

    /**
     * Check if any files were processed successfully
     */
    public function hasAnySuccess(): bool {
        return $this->getSuccessfulFilesCount() > 0;
    }
}