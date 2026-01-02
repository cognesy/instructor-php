<?php declare(strict_types=1);

namespace Cognesy\Metrics\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Stringable;
use Traversable;

/**
 * Dimensional metadata for metrics.
 *
 * Tags are key-value pairs that provide context for metrics,
 * enabling filtering, grouping, and aggregation by dimensions
 * like model, status, endpoint, etc.
 */
final readonly class Tags implements IteratorAggregate, Countable, Stringable
{
    /** @param array<string, string|int|float|bool> $values */
    public function __construct(
        private array $values = [],
    ) {}

    public static function empty(): self {
        return new self([]);
    }

    /** @param array<string, string|int|float|bool> $values */
    public static function of(array $values): self {
        return new self($values);
    }

    public function with(string $key, string|int|float|bool $value): self {
        return new self([...$this->values, $key => $value]);
    }

    public function without(string $key): self {
        $values = $this->values;
        unset($values[$key]);
        return new self($values);
    }

    public function merge(self $other): self {
        return new self([...$this->values, ...$other->values]);
    }

    public function get(string $key, mixed $default = null): mixed {
        return $this->values[$key] ?? $default;
    }

    public function has(string $key): bool {
        return isset($this->values[$key]);
    }

    public function isEmpty(): bool {
        return empty($this->values);
    }

    /** @return array<string, string|int|float|bool> */
    public function toArray(): array {
        return $this->values;
    }

    public function getIterator(): Traversable {
        return new ArrayIterator($this->values);
    }

    public function count(): int {
        return count($this->values);
    }

    /**
     * Returns a canonical string key for this tag set.
     * Used for aggregation and deduplication.
     */
    public function toKey(): string {
        $sorted = $this->values;
        ksort($sorted);
        $parts = [];
        foreach ($sorted as $key => $value) {
            $parts[] = "{$key}:{$value}";
        }
        return implode(',', $parts);
    }

    public function __toString(): string {
        if (empty($this->values)) {
            return '';
        }
        $parts = [];
        foreach ($this->values as $key => $value) {
            $escapedValue = str_replace('"', '\\"', (string) $value);
            $parts[] = sprintf('%s="%s"', $key, $escapedValue);
        }
        return implode(',', $parts);
    }
}
