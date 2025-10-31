<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Combinators;

use Cognesy\Experimental\Parser\Contracts\Parser;
use Cognesy\Experimental\Parser\Core\{BaseParser, ParserState, ParseResult};

/**
 * Parses zero or more occurrences of a parser.
 */
final class ManyParser extends BaseParser
{
    public function __construct(
        private readonly Parser $parser,
        private readonly int $minCount = 0,
    ) {}

    public function parse(ParserState $state): ParseResult
    {
        $results = [];
        $currentState = $state;

        while (!$currentState->isEOF()) {
            $result = $this->parser->parse($currentState);

            if ($result->isFailure()) {
                break;
            }

            $results[] = $result->value;
            $currentState = $result->state;
        }

        if (count($results) < $this->minCount) {
            return ParseResult::failure(
                error: "Expected at least {$this->minCount} occurrences, got " . count($results),
                state: $state,
            );
        }

        return ParseResult::success(
            value: $results,
            state: $currentState,
        );
    }
}
