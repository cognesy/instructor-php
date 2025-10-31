<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Core;

use Cognesy\Stream\Lexer\Data\Token;

/**
 * Represents the current state of parsing.
 * Tracks position in token stream and allows backtracking.
 */
final readonly class ParserState
{
    /**
     * @param Token[] $tokens
     */
    public function __construct(
        public array $tokens,
        public int $position = 0,
    ) {}

    public function current(): ?Token
    {
        return $this->tokens[$this->position] ?? null;
    }

    public function peek(int $offset = 1): ?Token
    {
        return $this->tokens[$this->position + $offset] ?? null;
    }

    public function advance(int $steps = 1): self
    {
        return new self(
            tokens: $this->tokens,
            position: $this->position + $steps,
        );
    }

    public function isEOF(): bool
    {
        return $this->position >= count($this->tokens);
    }

    public function remaining(): int
    {
        return count($this->tokens) - $this->position;
    }

    /**
     * Get slice of tokens from current position.
     */
    public function slice(int $length): array
    {
        return array_slice($this->tokens, $this->position, $length);
    }
}
