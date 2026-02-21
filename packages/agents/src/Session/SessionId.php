<?php declare(strict_types=1);

namespace Cognesy\Agents\Session;

use Cognesy\Utils\Uuid;

final readonly class SessionId
{
    private const PATTERN = '/\A[a-zA-Z0-9][a-zA-Z0-9_\-]*\z/';

    public string $value;

    public function __construct(string $value) {
        if (!preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException("Invalid session ID: {$value}");
        }
        $this->value = $value;
    }

    // CONSTRUCTORS ////////////////////////////////////////////////

    public static function generate(): self {
        return new self(Uuid::uuid4());
    }

    // ACCESSORS ///////////////////////////////////////////////////

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
