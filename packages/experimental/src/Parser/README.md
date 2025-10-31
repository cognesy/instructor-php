# Parser Infrastructure

Parser combinator library that bridges **Lexer** output (tokens) to **Interpreter** input (Programs/AST).

## Complete Pipeline

```
┌─────────────┐
│ Text Input  │  "2 + 3 * 4"
└──────┬──────┘
       │
       ▼
┌─────────────────────┐
│ LEXER               │
│ Stream/Transducers  │
└──────┬──────────────┘
       │
       ▼ [Token('NUMBER', '2'), Token('PLUS', '+'), ...]
       │
┌─────────────────────┐
│ PARSER              │
│ Combinators         │
└──────┬──────────────┘
       │
       ▼ Program (AST: BinaryOp(+, Literal(2), BinaryOp(*, ...)))
       │
┌─────────────────────┐
│ INTERPRETER         │
│ Monadic Execution   │
└──────┬──────────────┘
       │
       ▼ InterpreterState { value: 14, ... }
```

## Core Concepts

### ParserState

Immutable state tracking position in token stream:

```php
$state = new ParserState($tokens, position: 0);
$token = $state->current();      // Get current token
$next = $state->advance();       // Move to next token
$lookahead = $state->peek(2);    // Look ahead
```

### ParseResult

Result of parsing attempt (success or failure):

```php
$result = ParseResult::success(value: $ast, state: $newState);
$result = ParseResult::failure(error: "Expected NUMBER", state: $state);

if ($result->isSuccess()) {
    $value = $result->getValue();
}
```

### Parser Interface

All parsers implement:

```php
interface Parser {
    public function parse(ParserState $state): ParseResult;

    // Combinators
    public function map(callable $fn): Parser;
    public function then(Parser $next): Parser;
    public function or(Parser $alternative): Parser;
    public function many(): Parser;
    public function optional(): Parser;
}
```

## Basic Parsers

### Token Parser

Match a specific token type:

```php
use Cognesy\Experimental\Parser\ParserFactory as P;

$numberParser = P::token('NUMBER');
// Matches: Token(type='NUMBER', value='42')
```

### Literal Parser

Match exact token type and value:

```php
$plusParser = P::literal('OPERATOR', '+');
// Matches: Token(type='OPERATOR', value='+')
```

### Satisfy Parser

Match tokens satisfying a predicate:

```php
$evenNumberParser = P::satisfy(
    fn($token) => $token->type === 'NUMBER' && ((int)$token->value % 2 === 0),
    description: 'even number'
);
```

## Parser Combinators

### Sequence (`then`)

Parse two things in sequence:

```php
$assignmentParser = P::token('IDENTIFIER')
    ->then(P::literal('EQUALS', '='))
    ->then(P::token('NUMBER'));
// Parses: "x = 42"
// Returns: [Token(IDENTIFIER), Token(EQUALS), Token(NUMBER)]
```

### Choice (`or`)

Try alternatives (with backtracking):

```php
$valueParser = P::token('NUMBER')
    ->or(P::token('STRING'))
    ->or(P::token('IDENTIFIER'));
// Matches any of: NUMBER, STRING, or IDENTIFIER
```

### Many (`many`, `many1`)

Parse repetitions:

```php
$listParser = P::token('NUMBER')->many();
// Parses: "1 2 3 4" → [Token, Token, Token, Token]

$nonEmptyList = P::token('NUMBER')->many1();
// Requires at least one match
```

### Optional (`optional`)

Make a parser optional:

```php
$signParser = P::literal('OPERATOR', '-')->optional();
$numberParser = $signParser->then(P::token('NUMBER'));
// Parses: "-42" or "42"
```

### Map (`map`)

Transform parsed result:

```php
$intParser = P::token('NUMBER')
    ->map(fn($token) => (int) $token->value);
// Parses: Token('NUMBER', '42') → 42
```

## Advanced Combinators

### SepBy

Parse items separated by delimiter:

```php
$arrayParser = P::sepBy(
    itemParser: P::token('NUMBER'),
    separatorParser: P::literal('COMMA', ','),
    minCount: 0
);
// Parses: "1,2,3,4" → [Token, Token, Token, Token]
```

### Between

Parse content between delimiters:

```php
$parenExpr = P::between(
    open: P::literal('LPAREN', '('),
    close: P::literal('RPAREN', ')'),
    content: $exprParser
);
// Parses: "(expr)" → expr
```

### Chainl

Parse left-associative operators:

```php
$additionParser = P::chainl(
    termParser: $numberParser,
    opParser: P::literal('PLUS', '+')->map(fn() => fn($a, $b) => $a + $b)
);
// Parses: "1+2+3" → ((1+2)+3) = 6
```

### PrecedenceClimbing

Handle operator precedence correctly:

```php
$operators = [
    new Operator(tokenType: 'PLUS', precedence: 1),
    new Operator(tokenType: 'MUL', precedence: 2),
];

$exprParser = new PrecedenceClimbingParser(
    atomParser: $atomParser,
    operators: $operators
);
// Parses: "2+3*4" correctly as "2+(3*4)" = 14
```

## Building ASTs for the Interpreter

### ProgramBuilder

Converts parsed structures into Interpreter `Program` instances:

```php
use Cognesy\Experimental\Parser\Builders\ProgramBuilder;

// Create literal
$prog = ProgramBuilder::literal($numberToken);
// Creates: Literal(42)

// Create binary operation
$prog = ProgramBuilder::binaryOp('+', $left, $right);
// Creates: BinaryOperation(Add, $left, $right)

// Create variable reference
$prog = ProgramBuilder::variable($identifierToken);
// Creates: GetVar('x')

// Create assignment
$prog = ProgramBuilder::assignment('x', $valueExpr);
// Creates: SetVar('x', value)

// Create sequence
$prog = ProgramBuilder::sequence($prog1, $prog2, $prog3);
// Creates: $prog1->then($prog2)->then($prog3)
```

## Complete Example

```php
use Cognesy\Experimental\Parser\Examples\SimpleExpressionParser;

$parser = new SimpleExpressionParser();

// Parse and evaluate
$result = $parser->evaluate("2 + 3 * 4");
// Returns: 14

// Or get the Program for later execution
$program = $parser->parse("(5 + 3) * 2");
$state = $program(InterpreterState::initial());
// $state->value === 16
```

## Example: JSON Parser

```php
use Cognesy\Experimental\Parser\ParserFactory as P;
use Cognesy\Experimental\Parser\Builders\ProgramBuilder;

class JsonParser {
    public function value(): Parser {
        return P::choice(
            $this->null(),
            $this->boolean(),
            $this->number(),
            $this->string(),
            $this->array(),
            $this->object()
        );
    }

    public function null(): Parser {
        return P::token('NULL')
            ->map(fn($t) => ProgramBuilder::literal($t));
    }

    public function number(): Parser {
        return P::token('NUMBER')
            ->map(fn($t) => ProgramBuilder::literal($t));
    }

    public function string(): Parser {
        return P::token('STRING')
            ->map(fn($t) => ProgramBuilder::literal($t));
    }

    public function array(): Parser {
        return P::between(
            open: P::token('LBRACKET'),
            close: P::token('RBRACKET'),
            content: P::sepBy(
                itemParser: $this->value(),
                separatorParser: P::token('COMMA')
            )
        )->map(fn($items) => /* build array Program */);
    }

    public function object(): Parser {
        $keyValue = P::token('STRING')
            ->then(P::token('COLON'))
            ->then($this->value())
            ->map(fn($pair) => [$pair[0], $pair[2]]);

        return P::between(
            open: P::token('LBRACE'),
            close: P::token('RBRACE'),
            content: P::sepBy($keyValue, P::token('COMMA'))
        )->map(fn($pairs) => /* build object Program */);
    }
}
```

## Example: Simple Language Parser

```php
// Grammar:
//   program := statement*
//   statement := assignment | expression
//   assignment := IDENTIFIER '=' expression
//   expression := term (('+' | '-') term)*
//   term := factor (('*' | '/') factor)*
//   factor := NUMBER | IDENTIFIER | '(' expression ')'

class LanguageParser {
    public function program(): Parser {
        return $this->statement()->many()
            ->map(fn($stmts) => ProgramBuilder::sequence(...$stmts));
    }

    public function statement(): Parser {
        return $this->assignment()->or($this->expression());
    }

    public function assignment(): Parser {
        return P::token('IDENTIFIER')
            ->then(P::literal('EQUALS', '='))
            ->then($this->expression())
            ->map(fn($parts) =>
                ProgramBuilder::assignment($parts[0]->value, $parts[2])
            );
    }

    public function expression(): Parser {
        $operators = [
            new Operator('PLUS', 1, true, fn($l, $r) => ProgramBuilder::binaryOp('+', $l, $r)),
            new Operator('MINUS', 1, true, fn($l, $r) => ProgramBuilder::binaryOp('-', $l, $r)),
            new Operator('MUL', 2, true, fn($l, $r) => ProgramBuilder::binaryOp('*', $l, $r)),
            new Operator('DIV', 2, true, fn($l, $r) => ProgramBuilder::binaryOp('/', $l, $r)),
        ];

        return new PrecedenceClimbingParser(
            atomParser: $this->factor(),
            operators: $operators
        );
    }

    public function factor(): Parser {
        $number = P::token('NUMBER')
            ->map(fn($t) => ProgramBuilder::literal($t));

        $variable = P::token('IDENTIFIER')
            ->map(fn($t) => ProgramBuilder::variable($t));

        $parens = P::between(
            P::token('LPAREN'),
            P::token('RPAREN'),
            $this->expression()
        );

        return P::choice($parens, $number, $variable);
    }
}

// Usage:
$parser = new LanguageParser();
$input = "x = 5
y = x + 3
z = y * 2";

$tokens = /* lex input */;
$result = $parser->program()->parse(new ParserState($tokens));
$program = $result->getValue();

// Execute
$finalState = $program(InterpreterState::initial());
// $finalState->context->environment === ['x' => 5, 'y' => 8, 'z' => 16]
```

## Testing Parsers

```php
use PHPUnit\Framework\TestCase;

class ExpressionParserTest extends TestCase {
    public function test_parses_addition() {
        $parser = new SimpleExpressionParser();
        $result = $parser->evaluate("2 + 3");
        $this->assertEquals(5, $result);
    }

    public function test_respects_precedence() {
        $parser = new SimpleExpressionParser();
        $result = $parser->evaluate("2 + 3 * 4");
        $this->assertEquals(14, $result);
    }

    public function test_handles_parentheses() {
        $parser = new SimpleExpressionParser();
        $result = $parser->evaluate("(2 + 3) * 4");
        $this->assertEquals(20, $result);
    }
}
```

## Error Handling

```php
try {
    $program = $parser->parse($input);
} catch (\RuntimeException $e) {
    // $e->getMessage() includes position information
    echo "Parse error: " . $e->getMessage();
}
```

## Performance Tips

1. **Avoid left recursion** - Use iteration or precedence climbing instead
2. **Memoize recursive parsers** - Cache parse results for same position
3. **Fail fast** - Order alternatives from most to least specific
4. **Filter tokens early** - Remove whitespace/comments in lexer

## Debugging

```php
// Add tracing to see parse attempts
class TracingParser extends BaseParser {
    public function __construct(
        private Parser $inner,
        private string $name
    ) {}

    public function parse(ParserState $state): ParseResult {
        echo "Trying {$this->name} at position {$state->position}\n";
        $result = $this->inner->parse($state);
        echo "  → " . ($result->isSuccess() ? "Success" : "Failed") . "\n";
        return $result;
    }
}

$tracedParser = new TracingParser($numberParser, 'number');
```

## Architecture Benefits

1. **Composable** - Build complex parsers from simple ones
2. **Type-safe** - Parser combinators ensure correct types
3. **Testable** - Each parser can be tested independently
4. **Reusable** - Common patterns (expressions, lists) are reusable
5. **Declarative** - Grammar is expressed directly in code
6. **Backtracking** - Automatic via `or` combinator
7. **Error reporting** - Token positions available for messages

## Future Enhancements

- **Memoization** - Cache parse results (Packrat parsing)
- **Left recursion** - Support for left-recursive grammars
- **Error recovery** - Continue parsing after errors
- **Incremental parsing** - Re-parse only changed portions
- **Streaming parsing** - Parse without loading all tokens
