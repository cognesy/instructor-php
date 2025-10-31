<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Parsers;

use Cognesy\Experimental\Parser\Core\{BaseParser, ParserState, ParseResult};

/**
 * Parses a token with a specific type and value.
 */
final class LiteralParser extends BaseParser
{
    public function __construct(
        private readonly string $tokenType,
        private readonly string $expectedValue,
    ) {}

    public function parse(ParserState $state): ParseResult
    {
        if ($state->isEOF()) {
            return ParseResult::failure(
                error: "Expected '{$this->expectedValue}' but reached end of input",
                state: $state,
            );
        }

        $token = $state->current();

        if ($token->type === $this->tokenType && $token->value === $this->expectedValue) {
            return ParseResult::success(
                value: $token,
                state: $state->advance(),
            );
        }

        return ParseResult::failure(
            error: "Expected '{$this->expectedValue}' but got '{$token->value}' at {$token->position}",
            state: $state,
        );
    }
}
