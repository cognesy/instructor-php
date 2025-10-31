<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Combinators;

use Cognesy\Experimental\Parser\Contracts\Parser;
use Cognesy\Experimental\Parser\Core\{BaseParser, ParserState, ParseResult};

/**
 * Tries first parser, if it fails tries second (backtracking).
 */
final class ChoiceParser extends BaseParser
{
    public function __construct(
        private readonly Parser $first,
        private readonly Parser $second,
    ) {}

    public function parse(ParserState $state): ParseResult
    {
        $result1 = $this->first->parse($state);

        if ($result1->isSuccess()) {
            return $result1;
        }

        // Backtrack and try second parser
        return $this->second->parse($state);
    }
}
