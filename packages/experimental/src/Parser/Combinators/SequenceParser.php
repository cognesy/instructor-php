<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Combinators;

use Cognesy\Experimental\Parser\Contracts\Parser;
use Cognesy\Experimental\Parser\Core\{BaseParser, ParserState, ParseResult};

/**
 * Parses two parsers in sequence, returns array [result1, result2].
 */
final class SequenceParser extends BaseParser
{
    public function __construct(
        private readonly Parser $first,
        private readonly Parser $second,
    ) {}

    public function parse(ParserState $state): ParseResult
    {
        $result1 = $this->first->parse($state);

        if ($result1->isFailure()) {
            return $result1;
        }

        $result2 = $this->second->parse($result1->state);

        if ($result2->isFailure()) {
            return $result2;
        }

        return ParseResult::success(
            value: [$result1->value, $result2->value],
            state: $result2->state,
        );
    }
}
