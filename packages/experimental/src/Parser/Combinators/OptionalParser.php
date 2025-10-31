<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Combinators;

use Cognesy\Experimental\Parser\Contracts\Parser;
use Cognesy\Experimental\Parser\Core\{BaseParser, ParserState, ParseResult};

/**
 * Makes a parser optional - returns null if it fails.
 */
final class OptionalParser extends BaseParser
{
    public function __construct(
        private readonly Parser $parser,
    ) {}

    public function parse(ParserState $state): ParseResult
    {
        $result = $this->parser->parse($state);

        if ($result->isSuccess()) {
            return $result;
        }

        // Return success with null value
        return ParseResult::success(
            value: null,
            state: $state,
        );
    }
}
