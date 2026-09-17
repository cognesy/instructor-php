<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Data;

use Cognesy\Utils\Uuid;
use Stringable;

final readonly class DecisionAttemptId implements Stringable
{
    public function __construct(public string $value)
    {
        Uuid::assertValid($value);
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4());
    }

    public function toString(): string
    {
        return $this->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
