<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Parsers;

use Cognesy\Experimental\Parser\Core\{BaseParser, ParserState, ParseResult};

/**
 * Parses a specific token type.
 */
final class TokenParser extends BaseParser
{
    public function __construct(
        private readonly string $tokenType,
    ) {}

    public function parse(ParserState $state): ParseResult
    {
        if ($state->isEOF()) {
            return ParseResult::failure(
                error: "Expected {$this->tokenType} but reached end of input",
                state: $state,
            );
        }

        $token = $state->current();

        if ($token->type === $this->tokenType) {
            return ParseResult::success(
                value: $token,
                state: $state->advance(),
            );
        }

        return ParseResult::failure(
            error: "Expected {$this->tokenType} but got {$token->type} at {$token->position}",
            state: $state,
        );
    }
}
