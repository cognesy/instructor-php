<?php
declare(strict_types=1);

namespace Cognesy\Auxiliary\AstGrep\Data;

use Countable;
use Iterator;
use ArrayIterator;

class SearchResults implements Countable, Iterator
{
    private array $results;
    private ArrayIterator $iterator;

    public function __construct(array $results = []) {
        $this->results = $results;
        $this->iterator = new ArrayIterator($this->results);
    }

    public function add(SearchResult $result): self {
        $this->results[] = $result;
        $this->iterator = new ArrayIterator($this->results);
        return $this;
    }

    public function all(): array {
        return $this->results;
    }

    public function first(): ?SearchResult {
        return $this->results[0] ?? null;
    }

    public function last(): ?SearchResult {
        if (empty($this->results)) {
            return null;
        }
        return $this->results[count($this->results) - 1];
    }

    public function isEmpty(): bool {
        return empty($this->results);
    }

    public function isNotEmpty(): bool {
        return !$this->isEmpty();
    }

    public function count(): int {
        return count($this->results);
    }

    public function filter(callable $callback): self {
        return new self(array_values(array_filter($this->results, $callback)));
    }

    public function map(callable $callback): array {
        return array_map($callback, $this->results);
    }

    public function groupByFile(): array {
        $grouped = [];
        foreach ($this->results as $result) {
            $grouped[$result->file][] = $result;
        }
        return $grouped;
    }

    public function groupByDirectory(): array {
        $grouped = [];
        foreach ($this->results as $result) {
            $dir = dirname($result->file);
            $grouped[$dir][] = $result;
        }
        return $grouped;
    }

    public function getFiles(): array {
        return array_values(array_unique(array_map(fn($r) => $r->file, $this->results)));
    }

    public function getDirectories(): array {
        return array_values(array_unique(array_map(fn($r) => dirname($r->file), $this->results)));
    }

    public function sortByFile(): self {
        $results = $this->results;
        usort($results, fn($a, $b) => strcmp($a->file, $b->file) ?: $a->line <=> $b->line);
        return new self($results);
    }

    public function sortByLine(): self {
        $results = $this->results;
        usort($results, fn($a, $b) => $a->line <=> $b->line ?: strcmp($a->file, $b->file));
        return new self($results);
    }

    public function toArray(): array {
        return array_map(fn($r) => $r->toArray(), $this->results);
    }

    public function toJson(): string {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    public function current(): SearchResult {
        return $this->iterator->current();
    }

    public function key(): int {
        return $this->iterator->key();
    }

    public function next(): void {
        $this->iterator->next();
    }

    public function rewind(): void {
        $this->iterator->rewind();
    }

    public function valid(): bool {
        return $this->iterator->valid();
    }
}