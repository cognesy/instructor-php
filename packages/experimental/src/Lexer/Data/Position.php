<?php declare(strict_types=1);

namespace Cognesy\Experimental\Lexer\Data;

/**
 * Represents a position in source text (line and column).
 */
final readonly class Position
{
    public function __construct(
        public int $line = 1,
        public int $column = 1,
        public int $offset = 0,
    ) {}

    public function advance(string $char): self
    {
        return match ($char) {
            "\n" => new self(
                line: $this->line + 1,
                column: 1,
                offset: $this->offset + 1,
            ),
            "\r" => $this, // Ignore CR (CRLF handled by LF)
            default => new self(
                line: $this->line,
                column: $this->column + 1,
                offset: $this->offset + 1,
            ),
        };
    }

    public function __toString(): string
    {
        return "{$this->line}:{$this->column}";
    }
}
