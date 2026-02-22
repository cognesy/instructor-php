<?php declare(strict_types=1);

namespace Cognesy\Evals\ValueObject;

use Cognesy\Utils\Uuid;
use InvalidArgumentException;
use Stringable;

final readonly class ExperimentId implements Stringable
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        if ($value === '') {
            throw new InvalidArgumentException('ExperimentId cannot be empty');
        }

        return new self($value);
    }

    public static function generate(): self
    {
        return self::fromString(Uuid::uuid4());
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
