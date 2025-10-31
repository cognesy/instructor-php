<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Combinators;

use Cognesy\Experimental\Parser\Contracts\Parser;
use Cognesy\Experimental\Parser\Core\{BaseParser, ParserState, ParseResult};

/**
 * Parses items separated by a delimiter.
 * Example: sepBy(number, comma) parses "1,2,3"
 */
final class SepByParser extends BaseParser
{
    public function __construct(
        private readonly Parser $itemParser,
        private readonly Parser $separatorParser,
        private readonly int $minCount = 0,
    ) {}

    public function parse(ParserState $state): ParseResult
    {
        $items = [];
        $currentState = $state;

        // Try to parse first item
        $firstResult = $this->itemParser->parse($currentState);

        if ($firstResult->isFailure()) {
            if ($this->minCount > 0) {
                return $firstResult;
            }
            return ParseResult::success(value: [], state: $state);
        }

        $items[] = $firstResult->value;
        $currentState = $firstResult->state;

        // Parse (separator, item) pairs
        while (!$currentState->isEOF()) {
            $sepResult = $this->separatorParser->parse($currentState);

            if ($sepResult->isFailure()) {
                break;
            }

            $itemResult = $this->itemParser->parse($sepResult->state);

            if ($itemResult->isFailure()) {
                // Trailing separator - this is usually an error
                return ParseResult::failure(
                    error: "Expected item after separator",
                    state: $sepResult->state,
                );
            }

            $items[] = $itemResult->value;
            $currentState = $itemResult->state;
        }

        if (count($items) < $this->minCount) {
            return ParseResult::failure(
                error: "Expected at least {$this->minCount} items, got " . count($items),
                state: $state,
            );
        }

        return ParseResult::success(
            value: $items,
            state: $currentState,
        );
    }
}
