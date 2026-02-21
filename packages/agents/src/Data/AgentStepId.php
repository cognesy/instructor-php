<?php declare(strict_types=1);

namespace Cognesy\Agents\Data;

use Cognesy\Utils\Uuid;

final readonly class AgentStepId
{
    public function __construct(
        public string $value,
    ) {
        Uuid::assertValid($value);
    }

    public static function generate(): self {
        return new self(Uuid::uuid4());
    }

    public function toString(): string {
        return $this->value;
    }

    public function __toString(): string {
        return $this->value;
    }

    public function equals(self $other): bool {
        return $this->value === $other->value;
    }
}
