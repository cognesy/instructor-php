<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena;

final readonly class ObjectHash
{
    public const string ALGORITHM = 'sha256';

    public function __construct(
        private string $value,
    ) {
        if (preg_match('/\A[a-f0-9]{64}\z/', $value) !== 1) {
            throw new RecordException('Arena object hash must be a lowercase SHA-256 hex digest.');
        }
    }

    public static function fromBytes(string $bytes): self {
        return new self(hash(self::ALGORITHM, $bytes));
    }

    public function equals(self $other): bool {
        return hash_equals($this->value, $other->value);
    }

    public function toString(): string {
        return $this->value;
    }

    public function __toString(): string {
        return $this->value;
    }
}
