<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Examples;

use Cognesy\Experimental\Interpreter\InterpreterState;

/**
 * Complete end-to-end example showing:
 * Text → Lexer → Tokens → Parser → AST/Program → Interpreter → Result
 */
class EndToEndExample
{
    public static function runSimpleExpression(): void
    {
        echo "=== Simple Expression Example ===\n\n";

        $input = "2 + 3 * 4";
        echo "Input: $input\n";

        $parser = new SimpleExpressionParser();

        // Parse and execute
        $result = $parser->evaluate($input);
        echo "Result: $result\n";
        echo "Expected: 14\n\n";
    }

    public static function runWithParentheses(): void
    {
        echo "=== Expression with Parentheses ===\n\n";

        $input = "(2 + 3) * 4";
        echo "Input: $input\n";

        $parser = new SimpleExpressionParser();
        $result = $parser->evaluate($input);

        echo "Result: $result\n";
        echo "Expected: 20\n\n";
    }

    public static function runStepByStep(): void
    {
        echo "=== Step-by-Step Pipeline ===\n\n";

        $input = "5 - 2 + 1";
        echo "Input: $input\n\n";

        $parser = new SimpleExpressionParser();

        // Step 1: Get the Program (AST)
        echo "Step 1: Parse to AST\n";
        $program = $parser->parse($input);
        echo "  → Program created\n\n";

        // Step 2: Execute with interpreter
        echo "Step 2: Execute with interpreter\n";
        $initialState = InterpreterState::initial();
        echo "  Initial state: value=null\n";

        $finalState = $program($initialState);

        if ($finalState->isError) {
            echo "  Error: {$finalState->errorMessage}\n";
        } else {
            echo "  Final state: value={$finalState->value}\n";
            echo "  Expected: 4\n";
        }
        echo "\n";
    }

    public static function runComplexExpression(): void
    {
        echo "=== Complex Expression ===\n\n";

        $expressions = [
            "10 / 2",
            "3 + 4 * 2",
            "10 - 5 - 2",
            "(1 + 2) * (3 + 4)",
        ];

        $parser = new SimpleExpressionParser();

        foreach ($expressions as $expr) {
            try {
                $result = $parser->evaluate($expr);
                echo "$expr = $result\n";
            } catch (\Exception $e) {
                echo "$expr → Error: {$e->getMessage()}\n";
            }
        }
        echo "\n";
    }

    public static function runAll(): void
    {
        self::runSimpleExpression();
        self::runWithParentheses();
        self::runStepByStep();
        self::runComplexExpression();
    }

    public static function demonstratePipeline(): void
    {
        echo "=== Complete Pipeline Demonstration ===\n\n";
        echo "Pipeline: Text → Lexer → Parser → Interpreter\n\n";

        $input = "7 + 3";

        echo "1. INPUT TEXT\n";
        echo "   \"$input\"\n\n";

        $parser = new SimpleExpressionParser();

        // Manually demonstrate lexing
        echo "2. LEXER OUTPUT (Tokens)\n";
        $reflection = new \ReflectionClass($parser);
        $lexMethod = $reflection->getMethod('lex');
        
        $tokens = $lexMethod->invoke($parser, $input);

        foreach ($tokens as $token) {
            echo "   {$token->type}('{$token->value}') @ {$token->position}\n";
        }
        echo "\n";

        echo "3. PARSER OUTPUT (AST/Program)\n";
        $program = $parser->parse($input);
        echo "   Program instance created\n";
        echo "   Type: " . get_class($program) . "\n\n";

        echo "4. INTERPRETER EXECUTION\n";
        $state = $program(InterpreterState::initial());
        echo "   Initial state: value=null\n";
        echo "   Final state: value={$state->value}\n\n";

        echo "5. RESULT\n";
        echo "   $input = {$state->value}\n\n";
    }
}

// Uncomment to run examples:
// EndToEndExample::runAll();
// EndToEndExample::demonstratePipeline();
