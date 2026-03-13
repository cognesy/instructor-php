# Parser Architecture - Bridging Lexer and Interpreter

This document describes the complete parsing infrastructure that bridges the **Lexer** (in `/packages/stream/src/Lexer`) and the **Interpreter** (in `/packages/experimental/src/Interpreter`).

## Overview

```
┌──────────────────────────────────────────────────────────────┐
│                     COMPLETE PIPELINE                        │
└──────────────────────────────────────────────────────────────┘

Input: "2 + 3 * 4"
   ↓
┌──────────────────────────────────────────────────────────────┐
│ 1. LEXER (packages/stream/src/Lexer)                        │
│    - Character stream → Token stream                         │
│    - Position tracking (line, column, offset)                │
│    - Lazy evaluation via transducers                         │
└──────────────────────────────────────────────────────────────┘
   ↓
Tokens: [
  Token(NUMBER, "2", pos:1:1),
  Token(PLUS, "+", pos:1:3),
  Token(NUMBER, "3", pos:1:5),
  Token(MUL, "*", pos:1:7),
  Token(NUMBER, "4", pos:1:9)
]
   ↓
┌──────────────────────────────────────────────────────────────┐
│ 2. PARSER (packages/experimental/src/Parser)                 │
│    - Token stream → AST (Program)                            │
│    - Parser combinators (sequence, choice, many, etc.)       │
│    - Precedence climbing for expressions                     │
│    - Backtracking support                                    │
└──────────────────────────────────────────────────────────────┘
   ↓
AST: BinaryOp(Add,
       Literal(2),
       BinaryOp(Mul, Literal(3), Literal(4))
     )
   ↓
┌──────────────────────────────────────────────────────────────┐
│ 3. INTERPRETER (packages/experimental/src/Interpreter)       │
│    - Program → InterpreterState                              │
│    - Monadic execution model                                 │
│    - State threading (value + context)                       │
└──────────────────────────────────────────────────────────────┘
   ↓
Result: InterpreterState { value: 14, context: {...} }
```

## Component Breakdown

### 1. Lexer (packages/stream/src/Lexer)

**Purpose**: Convert text into positioned tokens.

**Key Components**:
- `Position` - Line/column/offset tracking
- `Token` - Lexical token with type, value, position
- `WithPosition` - Transducer for position tracking
- Format-specific lexers: JSON, YAML, CSV, HCL, INI

**Example**:
```php
use Cognesy\Stream\Transformation;
use Cognesy\Stream\Sources\Text\TextStream;
use Cognesy\Stream\Lexer\Lexers\JsonLexer;

$tokens = Transformation::define(...JsonLexer::create())
    ->withInput(TextStream::chars($jsonText))
    ->execute();
```

**Output**: `Token[]` with position information

---

### 2. Parser (packages/experimental/src/Parser)

**Purpose**: Convert tokens into AST (Abstract Syntax Tree) as Interpreter `Program` instances.

#### Core Types

**ParserState**: Tracks position in token stream
```php
class ParserState {
    public function __construct(
        public array $tokens,
        public int $position = 0
    ) {}

    public function current(): ?Token;
    public function advance(): self;
}
```

**ParseResult**: Success or failure with backtracking
```php
class ParseResult {
    public static function success(mixed $value, ParserState $state): self;
    public static function failure(string $error, ParserState $state): self;
}
```

**Parser Interface**: Core abstraction
```php
interface Parser {
    public function parse(ParserState $state): ParseResult;

    // Combinators
    public function map(callable $fn): Parser;
    public function then(Parser $next): Parser;
    public function or(Parser $alternative): Parser;
}
```

#### Parser Combinators

**Primitives**:
- `TokenParser` - Match specific token type
- `LiteralParser` - Match exact token value
- `SatisfyParser` - Match via predicate

**Combinators**:
- `SequenceParser` - Parse A then B
- `ChoiceParser` - Try A, if fails try B (backtracking)
- `ManyParser` - Parse 0+ occurrences
- `OptionalParser` - Make parser optional
- `MapParser` - Transform result

**Higher-Order**:
- `SepByParser` - Parse items with separators
- `BetweenParser` - Parse between delimiters
- `ChainlParser` - Left-associative operators
- `PrecedenceClimbingParser` - Expression parsing with precedence

**Example**:
```php
use Cognesy\Experimental\Parser\ParserFactory as P;

$numberParser = P::token('NUMBER');
$plusParser = P::literal('PLUS', '+');

$additionParser = P::chainl($numberParser, $plusParser);
// Parses: "1 + 2 + 3"
```

---

### 3. AST Builder (packages/experimental/src/Parser/Builders)

**Purpose**: Bridge Parser output to Interpreter input.

**ProgramBuilder**: Creates `Program` instances from parse results
```php
class ProgramBuilder {
    // Create literal instruction
    public static function literal(Token $token): Program;

    // Create variable operations
    public static function variable(Token $token): Program;
    public static function assignment(string $var, Program $value): Program;

    // Create binary operations
    public static function binaryOp(string $op, Program $left, Program $right): Program;

    // Create sequences
    public static function sequence(Program ...$programs): Program;
}
```

**Example**:
```php
// Parse "x = 5 + 3"
$assignmentParser = P::token('IDENTIFIER')
    ->then(P::literal('EQUALS', '='))
    ->then($exprParser)
    ->map(fn($parts) => ProgramBuilder::assignment(
        $parts[0]->value,
        $parts[2]
    ));
```

---

### 4. Interpreter (packages/experimental/src/Interpreter)

**Purpose**: Execute `Program` (AST) and produce results.

**InterpreterState**: Execution state
```php
class InterpreterState {
    public function __construct(
        public mixed $value,
        public InterpreterContext $context,
        public bool $isError = false,
        public ?string $errorMessage = null
    ) {}
}
```

**Program**: Executable AST node
```php
interface Program extends CanBeInterpreted {
    public function __invoke(InterpreterState $state): InterpreterState;
    public function then(CanMakeNextStep $next): Program;
}
```

**Available Operations**:
- `Literal` - Push value
- `GetVar` / `SetVar` - Variable operations
- `BinaryOperation` - Arithmetic/comparison
- `ReturnValue` - Return from computation

**Example**:
```php
use Cognesy\Experimental\Interpreter\Ops\{Literal, BinaryOperation};
use Cognesy\Experimental\Interpreter\Enums\BinaryOperatorType;

$program = new BinaryOperation(
    BinaryOperatorType::Add,
    2,
    3
);

$state = $program(InterpreterState::initial());
// $state->value === 5
```

---

## Complete Example: Expression Language

### Input
```
2 + 3 * 4
```

### Step 1: Lexing
```php
$lexer = new ExpressionLexer();
$tokens = $lexer->tokenize("2 + 3 * 4");

// Result:
[
  Token(NUMBER, "2"),
  Token(PLUS, "+"),
  Token(NUMBER, "3"),
  Token(MUL, "*"),
  Token(NUMBER, "4")
]
```

### Step 2: Parsing
```php
use Cognesy\Experimental\Parser\Examples\SimpleExpressionParser;

$parser = new SimpleExpressionParser();
$program = $parser->parse("2 + 3 * 4");

// Result: AST as Program
BinaryOperation(Add,
  Literal(2),
  BinaryOperation(Mul,
    Literal(3),
    Literal(4)
  )
)
```

### Step 3: Interpreting
```php
$state = $program(InterpreterState::initial());

// Result:
InterpreterState {
  value: 14,  // 2 + (3 * 4) = 14
  context: {...},
  isError: false
}
```

### All in One
```php
$parser = new SimpleExpressionParser();
$result = $parser->evaluate("2 + 3 * 4");
// → 14
```

---

## Usage Patterns

### Pattern 1: Direct Evaluation

```php
$parser = new SimpleExpressionParser();
$result = $parser->evaluate("(5 + 3) * 2");
// → 16
```

### Pattern 2: Build AST for Later

```php
$program = $parser->parse("x + 5");

// Execute multiple times with different contexts
$state1 = $program(InterpreterState::initial()->withContext(
    InterpreterContext::initial()->withEnvironment(['x' => 10])
));
// → 15

$state2 = $program(InterpreterState::initial()->withContext(
    InterpreterContext::initial()->withEnvironment(['x' => 20])
));
// → 25
```

### Pattern 3: Compose Programs

```php
$prog1 = $parser->parse("x = 5");
$prog2 = $parser->parse("y = x + 3");
$prog3 = $parser->parse("z = y * 2");

$combined = ProgramBuilder::sequence($prog1, $prog2, $prog3);
$finalState = $combined(InterpreterState::initial());

// $finalState->context->environment === ['x' => 5, 'y' => 8, 'z' => 16]
```

---

## File Structure

```
packages/
├── stream/src/Lexer/                    # LEXER
│   ├── Data/
│   │   ├── Position.php                 # Position tracking
│   │   ├── Token.php                    # Token representation
│   │   └── CharToken.php                # Positioned character
│   ├── Transducers/
│   │   ├── WithPosition.php             # Position tracking transducer
│   │   └── PatternMatcher.php           # Pattern matching
│   └── Lexers/
│       ├── JsonLexer.php                # JSON tokenizer
│       ├── CsvLexer.php                 # CSV tokenizer
│       └── ...
│
└── experimental/src/
    ├── Parser/                           # PARSER
    │   ├── Core/
    │   │   ├── ParserState.php          # Token stream state
    │   │   ├── ParseResult.php          # Parse outcome
    │   │   └── BaseParser.php           # Base implementation
    │   ├── Contracts/
    │   │   └── Parser.php               # Parser interface
    │   ├── Parsers/
    │   │   ├── TokenParser.php          # Match token type
    │   │   ├── LiteralParser.php        # Match literal
    │   │   ├── SatisfyParser.php        # Match predicate
    │   │   └── PrecedenceClimbingParser.php  # Expressions
    │   ├── Combinators/
    │   │   ├── SequenceParser.php       # A then B
    │   │   ├── ChoiceParser.php         # A or B
    │   │   ├── ManyParser.php           # A*
    │   │   ├── SepByParser.php          # A (sep A)*
    │   │   └── ...
    │   ├── Builders/
    │   │   └── ProgramBuilder.php       # AST → Program
    │   ├── Examples/
    │   │   ├── SimpleExpressionParser.php
    │   │   └── EndToEndExample.php
    │   └── README.md
    │
    └── Interpreter/                      # INTERPRETER
        ├── Contracts/
        │   ├── Program.php               # Executable AST
        │   └── CanBeInterpreted.php      # State transformer
        ├── Core/
        │   ├── Instruction.php           # Atomic operation
        │   └── InterpretableSequence.php # Sequencing
        ├── Ops/
        │   ├── Literal.php               # Literal values
        │   ├── BinaryOperation.php       # Binary ops
        │   ├── GetVar.php / SetVar.php   # Variables
        │   └── ...
        ├── InterpreterState.php          # Execution state
        └── InterpreterContext.php        # Environment
```

---

## Benefits

1. **Separation of Concerns**
   - Lexing: Text → Tokens
   - Parsing: Tokens → AST
   - Execution: AST → Result

2. **Composability**
   - Parsers compose via combinators
   - Programs compose via `then()`

3. **Reusability**
   - Lexers work for any parser
   - Parsers work for any interpreter
   - Common patterns shared across languages

4. **Testability**
   - Each component tested independently
   - Mock tokens for parser tests
   - Mock programs for interpreter tests

5. **Type Safety**
   - Strong types throughout pipeline
   - Parser combinators ensure correct composition

6. **Extensibility**
   - Add new lexers for new formats
   - Add new parsers for new grammars
   - Add new operations to interpreter

---

## Next Steps

1. **Create more example parsers**:
   - Full JSON parser
   - Simple programming language
   - Template language

2. **Optimize**:
   - Memoization (Packrat parsing)
   - Left recursion support

3. **Enhance error reporting**:
   - Better error messages with position
   - Error recovery mechanisms

4. **Add features**:
   - Incremental parsing
   - Streaming parser
   - Parse tree visualization
