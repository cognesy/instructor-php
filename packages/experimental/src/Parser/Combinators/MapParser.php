<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Combinators;

use Cognesy\Experimental\Parser\Contracts\Parser;
use Cognesy\Experimental\Parser\Core\{BaseParser, ParserState, ParseResult};

/**
 * Transforms the result of a parser with a function.
 */
final class MapParser extends BaseParser
{
    public function __construct(
        private readonly Parser $parser,
        private readonly \Closure $mapper,
    ) {}

    public function parse(ParserState $state): ParseResult
    {
        $result = $this->parser->parse($state);

        if ($result->isFailure()) {
            return $result;
        }

        return ParseResult::success(
            value: ($this->mapper)($result->value),
            state: $result->state,
        );
    }
}
