<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\State;

use Stringable;

final readonly class StateId implements Stringable
{
    public function __construct(
        public string $value,
    ) {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('State ID cannot be empty');
        }
    }

    public static function from(string $value): self {
        return new self($value);
    }

    public function toString(): string {
        return $this->value;
    }

    #[\Override]

    public function __toString(): string {
        return $this->value;
    }
}
