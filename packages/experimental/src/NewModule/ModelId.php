<?php declare(strict_types=1);

namespace Cognesy\Experimental\NewModule;

use Stringable;

final readonly class ModelId implements Stringable
{
    public function __construct(
        public string $value,
    ) {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('Model ID cannot be empty');
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
