<?php declare(strict_types=1);

namespace Cognesy\Experimental\Lexer\Data;

/**
 * Represents a single character with position information.
 * Used as intermediate representation before tokenization.
 */
final readonly class CharToken
{
    public function __construct(
        public string $char,
        public Position $position,
    ) {}

    public function __toString(): string
    {
        return $this->char;
    }
}
