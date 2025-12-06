<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Services;

use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;

final class BeadsJsonlFileService
{
    private JsonlParserService $parser;
    private JsonlGeneratorService $generator;

    /** @var IssueDTO[] */
    private array $issues = [];

    private ?string $currentFilePath = null;

    public function __construct(
        ?JsonlParserService $parser = null,
        ?JsonlGeneratorService $generator = null
    ) {
        $this->parser = $parser ?? new JsonlParserService();
        $this->generator = $generator ?? new JsonlGeneratorService();
    }

    /**
     * Read issues from a JSONL file into memory
     *
     * @return IssueDTO[]
     * @throws \RuntimeException if file cannot be read
     * @throws \JsonException if JSON is invalid
     * @throws \InvalidArgumentException if issue data is invalid
     */
    public function readFile(string $filePath): array
    {
        $this->issues = $this->parser->parseFile($filePath);
        $this->currentFilePath = $filePath;
        return $this->issues;
    }

    /**
     * Get all issues currently in memory
     *
     * @return IssueDTO[]
     */
    public function getAllIssues(): array
    {
        return $this->issues;
    }

    /**
     * Find an issue by its ID
     *
     * @throws \RuntimeException if issue not found
     */
    public function findById(string $id): IssueDTO
    {
        foreach ($this->issues as $issue) {
            if ($issue->id === $id) {
                return $issue;
            }
        }

        throw new \RuntimeException("Issue not found with ID: {$id}");
    }

    /**
     * Find an issue by its ID, returns null if not found
     */
    public function findByIdOrNull(string $id): ?IssueDTO
    {
        foreach ($this->issues as $issue) {
            if ($issue->id === $id) {
                return $issue;
            }
        }

        return null;
    }

    /**
     * Check if an issue exists by ID
     */
    public function exists(string $id): bool
    {
        return $this->findByIdOrNull($id) !== null;
    }

    /**
     * Add a new issue to the collection
     *
     * @throws \RuntimeException if issue with same ID already exists
     */
    public function addIssue(IssueDTO $issue): void
    {
        if ($this->exists($issue->id)) {
            throw new \RuntimeException("Issue already exists with ID: {$issue->id}");
        }

        $this->issues[] = $issue;
    }

    /**
     * Update an existing issue
     *
     * @throws \RuntimeException if issue not found
     */
    public function updateIssue(IssueDTO $issue): void
    {
        $found = false;
        foreach ($this->issues as $index => $existingIssue) {
            if ($existingIssue->id === $issue->id) {
                $this->issues[$index] = $issue;
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \RuntimeException("Issue not found with ID: {$issue->id}");
        }
    }

    /**
     * Add or update an issue (upsert)
     */
    public function upsertIssue(IssueDTO $issue): void
    {
        if ($this->exists($issue->id)) {
            $this->updateIssue($issue);
        } else {
            $this->addIssue($issue);
        }
    }

    /**
     * Remove an issue by ID
     *
     * @throws \RuntimeException if issue not found
     */
    public function removeIssue(string $id): void
    {
        $found = false;
        foreach ($this->issues as $index => $issue) {
            if ($issue->id === $id) {
                unset($this->issues[$index]);
                $this->issues = array_values($this->issues); // Re-index array
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \RuntimeException("Issue not found with ID: {$id}");
        }
    }

    /**
     * Write all issues to a file (overwrites existing file)
     *
     * @throws \RuntimeException if file cannot be written
     * @throws \JsonException if JSON encoding fails
     */
    public function writeFile(string $filePath): void
    {
        $this->generator->writeFile($filePath, $this->issues);
        $this->currentFilePath = $filePath;
    }

    /**
     * Write provided issues to a file (overwrites existing file).
     *
     * @param IssueDTO[] $issues
     */
    public function writeIssues(string $filePath, array $issues): void
    {
        $this->issues = $issues;
        $this->writeFile($filePath);
    }

    /**
     * Write issues back to the file they were read from
     *
     * @throws \RuntimeException if no file has been read or if file cannot be written
     */
    public function save(): void
    {
        if ($this->currentFilePath === null) {
            throw new \RuntimeException('No file loaded. Use readFile() first or specify a file path with writeFile()');
        }

        $this->writeFile($this->currentFilePath);
    }

    /**
     * Sort issues by ID
     */
    public function sortById(): void
    {
        usort($this->issues, fn(IssueDTO $a, IssueDTO $b) => $a->id <=> $b->id);
    }

    /**
     * Sort issues by a custom comparator
     *
     * @param callable(IssueDTO, IssueDTO): int $comparator
     */
    public function sort(callable $comparator): void
    {
        usort($this->issues, $comparator);
    }

    /**
     * Filter issues by a predicate
     *
     * @param callable(IssueDTO): bool $predicate
     * @return IssueDTO[]
     */
    public function filter(callable $predicate): array
    {
        return array_values(array_filter($this->issues, $predicate));
    }

    /**
     * Get the count of issues
     */
    public function count(): int
    {
        return count($this->issues);
    }

    /**
     * Clear all issues from memory
     */
    public function clear(): void
    {
        $this->issues = [];
        $this->currentFilePath = null;
    }
}
