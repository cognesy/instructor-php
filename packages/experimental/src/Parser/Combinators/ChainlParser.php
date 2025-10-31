<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Combinators;

use Cognesy\Experimental\Parser\Contracts\Parser;
use Cognesy\Experimental\Parser\Core\{BaseParser, ParserState, ParseResult};

/**
 * Parses left-associative operators.
 * Example: chainl(term, addOp) parses "1+2+3" as "((1+2)+3)"
 *
 * @param Parser $termParser Parser for terms
 * @param Parser $opParser Parser for operators (should return a combiner function)
 */
final class ChainlParser extends BaseParser
{
    public function __construct(
        private readonly Parser $termParser,
        private readonly Parser $opParser,
    ) {}

    public function parse(ParserState $state): ParseResult
    {
        // Parse first term
        $result = $this->termParser->parse($state);

        if ($result->isFailure()) {
            return $result;
        }

        $left = $result->value;
        $currentState = $result->state;

        // Parse (op term) pairs
        while (!$currentState->isEOF()) {
            $opResult = $this->opParser->parse($currentState);

            if ($opResult->isFailure()) {
                break;
            }

            $rightResult = $this->termParser->parse($opResult->state);

            if ($rightResult->isFailure()) {
                return ParseResult::failure(
                    error: "Expected term after operator",
                    state: $opResult->state,
                );
            }

            // Apply operator (opResult->value should be a combiner function)
            $combiner = $opResult->value;
            $left = $combiner($left, $rightResult->value);
            $currentState = $rightResult->state;
        }

        return ParseResult::success(
            value: $left,
            state: $currentState,
        );
    }
}
