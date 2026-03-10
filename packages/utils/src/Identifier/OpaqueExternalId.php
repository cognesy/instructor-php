<?php declare(strict_types=1);

namespace Cognesy\Utils\Identifier;

use Stringable;

abstract readonly class OpaqueExternalId implements Stringable
{
    public function __construct(
        public string $value = '',
    ) {}

    public static function fromString(string $value): static {
        return new static($value);
    }

    public static function null(): static {
        return new static('');
    }

    public function isEmpty(): bool {
        return trim($this->value) === '';
    }

    public function toString(): string {
        return $this->value;
    }

    public function toNullableString(): ?string {
        return $this->isEmpty() ? null : $this->value;
    }

    #[\Override]
    public function __toString(): string {
        return $this->value;
    }

    public function equals(self $other): bool {
        return $other::class === static::class
            && $other->value === $this->value;
    }
}
