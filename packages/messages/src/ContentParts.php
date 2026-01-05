<?php declare(strict_types=1);

namespace Cognesy\Messages;

final readonly class ContentParts
{
    /** @var ContentPart[] */
    private array $parts;

    public function __construct(ContentPart ...$parts) {
        $this->parts = $parts;
    }

    public static function empty(): self {
        return new self();
    }

    public static function fromArray(array $parts): self {
        $normalized = [];
        foreach ($parts as $part) {
            $normalized[] = match (true) {
                $part instanceof ContentPart => $part,
                default => ContentPart::fromAny($part),
            };
        }
        return new self(...$normalized);
    }

    /** @return ContentPart[] */
    public function all(): array {
        return $this->parts;
    }

    public function count(): int {
        return count($this->parts);
    }

    public function isEmpty(): bool {
        return $this->parts === [];
    }

    public function first(): ?ContentPart {
        return $this->parts[0] ?? null;
    }

    public function last(): ?ContentPart {
        $index = array_key_last($this->parts);
        return match (true) {
            $index === null => null,
            default => $this->parts[$index],
        };
    }

    public function add(ContentPart $part): self {
        $parts = $this->parts;
        $parts[] = $part;
        return new self(...$parts);
    }

    public function replaceLast(ContentPart $part): self {
        if ($this->parts === []) {
            return new self($part);
        }
        $parts = $this->parts;
        $parts[array_key_last($parts)] = $part;
        return new self(...$parts);
    }

    /** @return array<mixed> */
    public function map(callable $callback): array {
        return array_map($callback, $this->parts);
    }

    /** @return array<array<array-key, mixed>> */
    public function toArray(): array {
        return array_map(
            fn(ContentPart $part) => $part->toArray(),
            $this->parts,
        );
    }

    public function reduce(callable $callback, mixed $initial = null): mixed {
        return array_reduce($this->parts, $callback, $initial);
    }

    public function filter(callable $callback): self {
        return new self(...array_values(array_filter($this->parts, $callback)));
    }

    public function withoutEmpty(): self {
        return $this->filter(fn(ContentPart $part) => !$part->isEmpty());
    }

    public function toString(string $separator = "\n"): string {
        return implode(
            $separator,
            $this->withoutEmpty()->map(fn(ContentPart $part) => $part->toString()),
        );
    }
}
