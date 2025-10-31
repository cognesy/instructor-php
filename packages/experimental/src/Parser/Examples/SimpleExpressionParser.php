<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Examples;

use Cognesy\Experimental\Interpreter\Contracts\Program;
use Cognesy\Experimental\Interpreter\InterpreterState;
use Cognesy\Experimental\Parser\Contracts\Parser;
use Cognesy\Experimental\Parser\Core\ParserState;
use Cognesy\Experimental\Parser\ParserFactory;
use Cognesy\Experimental\Parser\Parsers\{Operator, PrecedenceClimbingParser};
use Cognesy\Experimental\Parser\Builders\ProgramBuilder;
use Cognesy\Stream\Transformation;
use Cognesy\Stream\Sources\Text\TextStream;
use Cognesy\Stream\Transform\Filter\Transducers\Filter;

/**
 * Example: Simple arithmetic expression parser.
 *
 * Grammar:
 *   expr   := term (('+' | '-') term)*
 *   term   := factor (('*' | '/') factor)*
 *   factor := NUMBER | '(' expr ')'
 *
 * Example input: "2 + 3 * 4"
 * Output: Interpreter Program that evaluates to 14
 */
class SimpleExpressionParser
{
    private Parser $exprParser;

    public function __construct()
    {
        // Define operators with precedence
        $operators = [
            new Operator(
                tokenType: 'PLUS',
                precedence: 1,
                leftAssociative: true,
                combiner: fn($left, $right) => ProgramBuilder::binaryOp('+', $left, $right)
            ),
            new Operator(
                tokenType: 'MINUS',
                precedence: 1,
                leftAssociative: true,
                combiner: fn($left, $right) => ProgramBuilder::binaryOp('-', $left, $right)
            ),
            new Operator(
                tokenType: 'MUL',
                precedence: 2,
                leftAssociative: true,
                combiner: fn($left, $right) => ProgramBuilder::binaryOp('*', $left, $right)
            ),
            new Operator(
                tokenType: 'DIV',
                precedence: 2,
                leftAssociative: true,
                combiner: fn($left, $right) => ProgramBuilder::binaryOp('/', $left, $right)
            ),
        ];

        // Build expression parser
        $this->exprParser = new PrecedenceClimbingParser(
            atomParser: $this->atomParser(),
            operators: $operators,
        );
    }

    /**
     * Parse text into an Interpreter Program.
     */
    public function parse(string $input): Program
    {
        // Step 1: Lexing - convert text to tokens
        $tokens = $this->lex($input);

        // Step 2: Parsing - convert tokens to Program (AST)
        $parserState = new ParserState($tokens);
        $result = $this->exprParser->parse($parserState);

        if ($result->isFailure()) {
            throw new \RuntimeException("Parse error: " . $result->error);
        }

        return $result->value;
    }

    /**
     * Parse and evaluate in one step.
     */
    public function evaluate(string $input): mixed
    {
        $program = $this->parse($input);
        $state = $program(InterpreterState::initial());

        if ($state->isError) {
            throw new \RuntimeException("Evaluation error: " . $state->errorMessage);
        }

        return $state->value;
    }

    /**
     * Lexer for simple expressions.
     */
    private function lex(string $input): array
    {
        return Transformation::define(
            ...$this->createLexer(),
            // Filter out whitespace
            new Filter(fn($token) => $token->type !== 'WHITESPACE')
        )
            ->withInput(TextStream::chars($input))
            ->execute();
    }

    /**
     * Create a simple expression lexer.
     */
    private function createLexer(): array
    {
        return [
            new \Cognesy\Stream\Lexer\Transducers\WithPosition(),
            new \Cognesy\Stream\Lexer\Transducers\PatternMatcher(
                rules: [
                    \Cognesy\Stream\Lexer\Data\LexerRule::pattern('/\d/', 'NUMBER'),
                    \Cognesy\Stream\Lexer\Data\LexerRule::char('+', 'PLUS'),
                    \Cognesy\Stream\Lexer\Data\LexerRule::char('-', 'MINUS'),
                    \Cognesy\Stream\Lexer\Data\LexerRule::char('*', 'MUL'),
                    \Cognesy\Stream\Lexer\Data\LexerRule::char('/', 'DIV'),
                    \Cognesy\Stream\Lexer\Data\LexerRule::char('(', 'LPAREN'),
                    \Cognesy\Stream\Lexer\Data\LexerRule::char(')', 'RPAREN'),
                    \Cognesy\Stream\Lexer\Data\LexerRule::whitespace('WHITESPACE'),
                ],
                defaultTokenType: 'UNKNOWN',
            ),
        ];
    }

    /**
     * Parser for atomic expressions (numbers and parenthesized expressions).
     */
    private function atomParser(): Parser
    {
        // NUMBER
        $numberParser = ParserFactory::token('NUMBER')
            ->map(fn($token) => ProgramBuilder::literal($token));

        // '(' expr ')'
        $parenParser = ParserFactory::between(
            open: ParserFactory::token('LPAREN'),
            close: ParserFactory::token('RPAREN'),
            content: new class($this) extends \Cognesy\Experimental\Parser\Core\BaseParser {
                public function __construct(private SimpleExpressionParser $parent) {}

                public function parse(ParserState $state): \Cognesy\Experimental\Parser\Core\ParseResult
                {
                    return $this->parent->exprParser->parse($state);
                }
            }
        );

        // Try parenthesized expression first, then number
        return $parenParser->or($numberParser);
    }
}
