<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Value;

use InvalidArgumentException;

final readonly class TraceId
{
    public function __construct(
        private string $value,
    ) {
        if (!preg_match('/^[0-9a-f]{32}$/', $value)) {
            throw new InvalidArgumentException('TraceId must be a 32-character lowercase hex string.');
        }
    }

    public static function generate(): self {
        return new self(bin2hex(random_bytes(16)));
    }

    public static function fromString(string $value): self {
        return new self(strtolower($value));
    }

    public function value(): string {
        return $this->value;
    }
}
