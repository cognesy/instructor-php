<?php declare(strict_types=1);

namespace Cognesy\Experimental\Lexer\Data;

use Closure;

/**
 * Represents a lexer rule for pattern matching.
 */
final readonly class LexerRule
{
    /**
     * @param Closure(CharToken, CharToken[]): bool $matcher Function that determines if rule matches
     * @param string $tokenType Type of token to emit
     * @param bool $includeTerminator Whether the matching character should be included in token
     */
    public function __construct(
        public Closure $matcher,
        public string $tokenType,
        public bool $includeTerminator = false,
    ) {}

    public function matches(CharToken $char, array $buffer): bool
    {
        return ($this->matcher)($char, $buffer);
    }

    /**
     * Create rule that matches a specific character.
     */
    public static function char(string $char, string $tokenType, bool $includeTerminator = true): self
    {
        return new self(
            matcher: fn(CharToken $c) => $c->char === $char,
            tokenType: $tokenType,
            includeTerminator: $includeTerminator,
        );
    }

    /**
     * Create rule that matches any character in a set.
     */
    public static function anyOf(string $chars, string $tokenType, bool $includeTerminator = true): self
    {
        return new self(
            matcher: fn(CharToken $c) => str_contains($chars, $c->char),
            tokenType: $tokenType,
            includeTerminator: $includeTerminator,
        );
    }

    /**
     * Create rule that matches a regex pattern.
     */
    public static function pattern(string $pattern, string $tokenType, bool $includeTerminator = true): self
    {
        return new self(
            matcher: fn(CharToken $c) => (bool) preg_match($pattern, $c->char),
            tokenType: $tokenType,
            includeTerminator: $includeTerminator,
        );
    }

    /**
     * Create rule that matches whitespace.
     */
    public static function whitespace(string $tokenType = 'WHITESPACE', bool $includeTerminator = true): self
    {
        return self::pattern('/\s/', $tokenType, $includeTerminator);
    }

    /**
     * Create rule that matches digits.
     */
    public static function digit(string $tokenType = 'DIGIT', bool $includeTerminator = true): self
    {
        return self::pattern('/\d/', $tokenType, $includeTerminator);
    }

    /**
     * Create rule that matches letters.
     */
    public static function letter(string $tokenType = 'LETTER', bool $includeTerminator = true): self
    {
        return self::pattern('/[a-zA-Z]/', $tokenType, $includeTerminator);
    }

    /**
     * Create rule that matches alphanumeric characters.
     */
    public static function alphanumeric(string $tokenType = 'ALPHANUM', bool $includeTerminator = true): self
    {
        return self::pattern('/[a-zA-Z0-9]/', $tokenType, $includeTerminator);
    }
}
