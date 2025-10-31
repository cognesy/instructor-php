<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Combinators;

use Cognesy\Experimental\Parser\Contracts\Parser;
use Cognesy\Experimental\Parser\Core\{BaseParser, ParserState, ParseResult};

/**
 * Parses content between opening and closing parsers.
 * Example: between(lparen, rparen, expr) parses "(expr)"
 */
final class BetweenParser extends BaseParser
{
    public function __construct(
        private readonly Parser $open,
        private readonly Parser $close,
        private readonly Parser $content,
    ) {}

    public function parse(ParserState $state): ParseResult
    {
        $openResult = $this->open->parse($state);

        if ($openResult->isFailure()) {
            return $openResult;
        }

        $contentResult = $this->content->parse($openResult->state);

        if ($contentResult->isFailure()) {
            return $contentResult;
        }

        $closeResult = $this->close->parse($contentResult->state);

        if ($closeResult->isFailure()) {
            return $closeResult;
        }

        return ParseResult::success(
            value: $contentResult->value,
            state: $closeResult->state,
        );
    }
}
