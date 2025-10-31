<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Parsers;

use Cognesy\Experimental\Parser\Contracts\Parser;
use Cognesy\Experimental\Parser\Core\{BaseParser, ParserState, ParseResult};

/**
 * Operator precedence and associativity.
 */
final readonly class Operator
{
    public function __construct(
        public string $tokenType,
        public int $precedence,
        public bool $leftAssociative = true,
        public ?\Closure $combiner = null,
    ) {}
}

/**
 * Precedence climbing parser for expressions with infix operators.
 * Handles operator precedence and associativity correctly.
 */
final class PrecedenceClimbingParser extends BaseParser
{
    /**
     * @param Parser $atomParser Parser for atomic expressions (numbers, variables, etc.)
     * @param Operator[] $operators List of operators with precedence
     */
    public function __construct(
        private readonly Parser $atomParser,
        private readonly array $operators,
    ) {}

    public function parse(ParserState $state): ParseResult
    {
        return $this->parseExpr($state, minPrec: 0);
    }

    private function parseExpr(ParserState $state, int $minPrec): ParseResult
    {
        // Parse left-hand side (atom or prefix expression)
        $leftResult = $this->atomParser->parse($state);

        if ($leftResult->isFailure()) {
            return $leftResult;
        }

        $left = $leftResult->value;
        $currentState = $leftResult->state;

        // Process infix operators
        while (!$currentState->isEOF()) {
            $op = $this->findOperator($currentState);

            if ($op === null || $op->precedence < $minPrec) {
                break;
            }

            // Consume operator token
            $currentState = $currentState->advance();

            // Calculate next minimum precedence
            $nextMinPrec = $op->leftAssociative
                ? $op->precedence + 1
                : $op->precedence;

            // Parse right-hand side
            $rightResult = $this->parseExpr($currentState, $nextMinPrec);

            if ($rightResult->isFailure()) {
                return $rightResult;
            }

            // Combine left and right with operator
            if ($op->combiner !== null) {
                $left = ($op->combiner)($left, $rightResult->value);
            } else {
                $left = [$op->tokenType, $left, $rightResult->value];
            }

            $currentState = $rightResult->state;
        }

        return ParseResult::success(
            value: $left,
            state: $currentState,
        );
    }

    private function findOperator(ParserState $state): ?Operator
    {
        if ($state->isEOF()) {
            return null;
        }

        $token = $state->current();

        foreach ($this->operators as $op) {
            if ($token->type === $op->tokenType) {
                return $op;
            }
        }

        return null;
    }
}
