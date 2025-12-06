<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Services;

use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;

final class JsonlGeneratorService
{
    /**
     * Generate JSONL content from an array of IssueDTO objects
     *
     * @param IssueDTO[] $issues
     * @throws \JsonException if JSON encoding fails
     */
    public function generateContent(array $issues): string
    {
        $lines = [];

        foreach ($issues as $issue) {
            if (!$issue instanceof IssueDTO) {
                throw new \InvalidArgumentException('All items must be instances of IssueDTO');
            }

            $lines[] = $this->generateLine($issue);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Generate a single JSON line from an IssueDTO object
     *
     * @throws \JsonException if JSON encoding fails
     */
    public function generateLine(IssueDTO $issue): string
    {
        return json_encode(
            $issue->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Write issues to a JSONL file
     *
     * @param IssueDTO[] $issues
     * @throws \RuntimeException if file cannot be written
     * @throws \JsonException if JSON encoding fails
     */
    public function writeFile(string $filePath, array $issues): void
    {
        $directory = dirname($filePath);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException("Failed to create directory: {$directory}");
            }
        }

        if (file_exists($filePath) && !is_writable($filePath)) {
            throw new \RuntimeException("File is not writable: {$filePath}");
        }

        $content = $this->generateContent($issues);

        if (file_put_contents($filePath, $content) === false) {
            throw new \RuntimeException("Failed to write file: {$filePath}");
        }
    }

    /**
     * Append an issue to an existing JSONL file
     *
     * @throws \RuntimeException if file cannot be written
     * @throws \JsonException if JSON encoding fails
     */
    public function appendToFile(string $filePath, IssueDTO $issue): void
    {
        $directory = dirname($filePath);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException("Failed to create directory: {$directory}");
            }
        }

        $line = $this->generateLine($issue) . "\n";

        if (file_put_contents($filePath, $line, FILE_APPEND) === false) {
            throw new \RuntimeException("Failed to append to file: {$filePath}");
        }
    }
}
