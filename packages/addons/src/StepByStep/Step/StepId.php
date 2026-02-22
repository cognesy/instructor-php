<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Step;

use Stringable;

final readonly class StepId implements Stringable
{
    public function __construct(
        public string $value,
    ) {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('Step ID cannot be empty');
        }
    }

    public static function from(string $value): self {
        return new self($value);
    }

    public function toString(): string {
        return $this->value;
    }

    public function __toString(): string {
        return $this->value;
    }
}
