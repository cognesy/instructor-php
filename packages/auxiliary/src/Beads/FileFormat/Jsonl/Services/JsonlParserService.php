<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Services;

use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;

final class JsonlParserService
{
    /**
     * Parse a JSONL file and return an array of IssueDTO objects
     *
     * @return IssueDTO[]
     * @throws \RuntimeException if file cannot be read
     * @throws \JsonException if JSON is invalid
     * @throws \InvalidArgumentException if issue data is invalid
     */
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new \RuntimeException("File is not readable: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$filePath}");
        }

        return $this->parseContent($content);
    }

    /**
     * Parse JSONL content and return an array of IssueDTO objects
     *
     * @return IssueDTO[]
     * @throws \JsonException if JSON is invalid
     * @throws \InvalidArgumentException if issue data is invalid
     */
    public function parseContent(string $content): array
    {
        $lines = array_filter(
            explode("\n", $content),
            fn($line) => trim($line) !== ''
        );

        $issues = [];
        $lineNumber = 0;

        foreach ($lines as $line) {
            $lineNumber++;
            try {
                $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $issues[] = IssueDTO::fromArray($data);
            } catch (\JsonException $e) {
                throw new \JsonException(
                    "Invalid JSON on line {$lineNumber}: " . $e->getMessage(),
                    $e->getCode(),
                    $e
                );
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException(
                    "Invalid issue data on line {$lineNumber}: " . $e->getMessage(),
                    $e->getCode(),
                    $e
                );
            }
        }

        return $issues;
    }

    /**
     * Parse a single JSON line and return an IssueDTO object
     *
     * @throws \JsonException if JSON is invalid
     * @throws \InvalidArgumentException if issue data is invalid
     */
    public function parseLine(string $line): IssueDTO
    {
        $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        return IssueDTO::fromArray($data);
    }
}
