<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Value;

final readonly class TraceFlags
{
    public function __construct(
        private string $value,
    ) {}

    public static function sampled(): self {
        return new self('01');
    }

    public static function notSampled(): self {
        return new self('00');
    }

    public static function fromString(string $value): self {
        return new self(strtolower($value));
    }

    public function value(): string {
        return $this->value;
    }
}
