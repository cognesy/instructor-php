<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Contracts;

use Cognesy\Experimental\Parser\Core\{ParserState, ParseResult};

/**
 * A parser is a function: ParserState -> ParseResult
 * Parsers can be composed to build complex grammars.
 */
interface Parser
{
    public function parse(ParserState $state): ParseResult;

    /**
     * Map the parsed value.
     */
    public function map(callable $fn): Parser;

    /**
     * Chain parsers sequentially.
     */
    public function then(Parser $next): Parser;

    /**
     * Try this parser, if it fails try the alternative.
     */
    public function or(Parser $alternative): Parser;

    /**
     * Parse many occurrences (0 or more).
     */
    public function many(): Parser;

    /**
     * Parse at least one occurrence.
     */
    public function many1(): Parser;

    /**
     * Make this parser optional (returns null if fails).
     */
    public function optional(): Parser;
}
