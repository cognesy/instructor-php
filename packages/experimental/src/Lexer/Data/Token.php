<?php declare(strict_types=1);

namespace Cognesy\Experimental\Lexer\Data;

/**
 * Represents a lexical token with its type, value, and position.
 */
final readonly class Token
{
    public function __construct(
        public string $type,
        public string $value,
        public Position $position,
        public ?Position $endPosition = null,
    ) {}

    public function is(string $type): bool
    {
        return $this->type === $type;
    }

    public function isOneOf(string ...$types): bool
    {
        return in_array($this->type, $types, strict: true);
    }

    public function withValue(string $value): self
    {
        return new self(
            type: $this->type,
            value: $value,
            position: $this->position,
            endPosition: $this->endPosition,
        );
    }

    public function withType(string $type): self
    {
        return new self(
            type: $type,
            value: $this->value,
            position: $this->position,
            endPosition: $this->endPosition,
        );
    }

    public function withEndPosition(Position $endPosition): self
    {
        return new self(
            type: $this->type,
            value: $this->value,
            position: $this->position,
            endPosition: $endPosition,
        );
    }

    public function __toString(): string
    {
        return sprintf(
            "%s('%s') @ %s",
            $this->type,
            addcslashes($this->value, "\n\r\t"),
            $this->position
        );
    }
}
