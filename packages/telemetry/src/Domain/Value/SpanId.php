<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Value;

use InvalidArgumentException;

final readonly class SpanId
{
    public function __construct(
        private string $value,
    ) {
        if (!preg_match('/^[0-9a-f]{16}$/', $value)) {
            throw new InvalidArgumentException('SpanId must be a 16-character lowercase hex string.');
        }
    }

    public static function generate(): self {
        return new self(bin2hex(random_bytes(8)));
    }

    public static function fromString(string $value): self {
        return new self(strtolower($value));
    }

    public function value(): string {
        return $this->value;
    }
}
