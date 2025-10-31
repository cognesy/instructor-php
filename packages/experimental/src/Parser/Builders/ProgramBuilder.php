<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Builders;

use Cognesy\Experimental\Interpreter\Contracts\Program;
use Cognesy\Experimental\Interpreter\Core\Instruction;
use Cognesy\Experimental\Interpreter\Enums\BinaryOperatorType;
use Cognesy\Experimental\Interpreter\Ops\{Literal, BinaryOperation, GetVar, SetVar};
use Cognesy\Stream\Lexer\Data\Token;

/**
 * Builds Interpreter Program instances from parsed structures.
 * Bridges the parser output to the interpreter input.
 */
class ProgramBuilder
{
    /**
     * Create a literal instruction from a token.
     */
    public static function literal(Token $token): Program
    {
        $value = match ($token->type) {
            'NUMBER' => (float) $token->value,
            'STRING' => $token->value,
            'TRUE' => true,
            'FALSE' => false,
            'NULL' => null,
            default => $token->value,
        };

        return new Literal($value);
    }

    /**
     * Create a variable reference instruction.
     */
    public static function variable(Token $token): Program
    {
        return new GetVar($token->value);
    }

    /**
     * Create a variable assignment instruction.
     */
    public static function assignment(string $varName, Program $valueExpr): Program
    {
        // This is tricky - we need to evaluate the expression first, then set the variable
        // We'll use the Program::then() combinator
        return $valueExpr->then(
            \Cognesy\Experimental\Interpreter\Core\Continued::with(
                fn($state) => (new SetVar($varName, $state->value))
            )
        );
    }

    /**
     * Create a binary operation instruction.
     */
    public static function binaryOp(string $operator, Program $left, Program $right): Program
    {
        $opType = self::mapOperator($operator);

        // Evaluate left, then right, then combine
        return $left->then(
            \Cognesy\Experimental\Interpreter\Core\Continued::with(
                fn($leftState) => $right->then(
                    \Cognesy\Experimental\Interpreter\Core\Continued::with(
                        fn($rightState) => new BinaryOperation(
                            $opType,
                            $leftState->value,
                            $rightState->value
                        )
                    )
                )
            )
        );
    }

    /**
     * Create a sequence of programs.
     */
    public static function sequence(Program ...$programs): Program
    {
        if (empty($programs)) {
            return new Literal(null);
        }

        $first = array_shift($programs);

        return array_reduce(
            $programs,
            fn(Program $acc, Program $p) => $acc->then(
                \Cognesy\Experimental\Interpreter\Core\Continued::withIgnoredOutput($p)
            ),
            $first
        );
    }

    /**
     * Map operator token to BinaryOperatorType.
     */
    private static function mapOperator(string $operator): BinaryOperatorType
    {
        return match ($operator) {
            '+', 'PLUS' => BinaryOperatorType::Add,
            '-', 'MINUS' => BinaryOperatorType::Sub,
            '*', 'MUL' => BinaryOperatorType::Mul,
            '/', 'DIV' => BinaryOperatorType::Div,
            '>', 'GT' => BinaryOperatorType::Gt,
            '<', 'LT' => BinaryOperatorType::Lt,
            '>=', 'GTE' => BinaryOperatorType::Gte,
            '<=', 'LTE' => BinaryOperatorType::Lte,
            '==', 'EQ' => BinaryOperatorType::Eq,
            '!=', 'NEQ' => BinaryOperatorType::Neq,
            default => throw new \InvalidArgumentException("Unknown operator: $operator"),
        };
    }
}
