<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Parsers;

use Cognesy\Experimental\Parser\Core\{BaseParser, ParserState, ParseResult};
use Cognesy\Stream\Lexer\Data\Token;

/**
 * Parses a token that satisfies a predicate.
 */
final class SatisfyParser extends BaseParser
{
    public function __construct(
        private readonly \Closure $predicate,
        private readonly string $expectedDescription = 'token matching predicate',
    ) {}

    public function parse(ParserState $state): ParseResult
    {
        if ($state->isEOF()) {
            return ParseResult::failure(
                error: "Expected {$this->expectedDescription} but reached end of input",
                state: $state,
            );
        }

        $token = $state->current();

        if (($this->predicate)($token)) {
            return ParseResult::success(
                value: $token,
                state: $state->advance(),
            );
        }

        return ParseResult::failure(
            error: "Expected {$this->expectedDescription} but got {$token->type}",
            state: $state,
        );
    }
}
