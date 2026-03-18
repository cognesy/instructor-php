<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Value;

use JsonSerializable;

final readonly class AttributeBag implements JsonSerializable
{
    /** @param array<string, scalar|array<array-key, scalar>|null> $items */
    public function __construct(
        private array $items = [],
    ) {}

    public static function empty(): self {
        return new self();
    }

    /** @param scalar|array<array-key, scalar>|null $value */
    public function with(string $key, mixed $value): self {
        $items = $this->items;
        $items[$key] = $value;
        return new self($items);
    }

    public function merge(self $other): self
    {
        return new self([...$this->items, ...$other->items]);
    }

    /** @param array<string, scalar|array<array-key, scalar>|null> $items */
    public static function fromArray(array $items): self {
        return new self($items);
    }

    /** @return array<string, scalar|array<array-key, scalar>|null> */
    public function toArray(): array {
        return $this->items;
    }

    #[\Override]
    public function jsonSerialize(): array {
        return $this->items;
    }
}
