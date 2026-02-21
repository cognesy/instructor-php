<?php declare(strict_types=1);

namespace Cognesy\Utils\Identifier;

abstract readonly class OpaqueExternalId
{
    public function __construct(
        public string $value,
    ) {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('External ID cannot be empty');
        }
    }

    public static function fromString(string $value): static {
        return new static($value);
    }

    public function toString(): string {
        return $this->value;
    }

    public function __toString(): string {
        return $this->value;
    }

    public function equals(self $other): bool {
        return $other::class === static::class
            && $other->value === $this->value;
    }
}
