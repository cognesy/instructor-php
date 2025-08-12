# DSPy-PHP: Top-Down Implementation Plan

*Building modular, optimizable language programs in PHP 8.2+ with strong types and domain-driven design*

---

## 1. Developer Experience (Top Level)

### Target Usage Pattern

```php
// 1. Define a QA program
class QuestionAnswerProgram extends CompositeModule
{
    public function __construct() {
        $this->expand = new PredictModule(
            signature: 'question: string -> subquestions: array<string>',
            description: 'Break down complex questions into simpler sub-questions'
        );
        
        $this->aggregate = new PredictModule(
            signature: 'subquestions: array<string>, facts: array<string> -> context: string',
            description: 'Combine facts into coherent context'
        );
        
        $this->answer = new PredictModule(
            signature: 'question: string, context: string -> answer: string',
            description: 'Generate final answer from context'
        );
    }
    
    protected function forward(QuestionInput $input, Tracer $tracer): Prediction {
        $subquestions = $this->expand->forward($input, $tracer);
        $facts = $this->searchFacts($subquestions->subquestions); // External service
        $context = $this->aggregate->forward(new AggregationInput($subquestions->subquestions, $facts), $tracer);
        $answer = $this->answer->forward(new AnswerInput($input->question, $context->context), $tracer);
        
        return new Prediction(new AnswerOutput($answer->answer));
    }
}

// 2. Use the program
$program = new QuestionAnswerProgram();
$result = $program->execute(new QuestionInput('What are the main causes of climate change?'));
echo $result->answer; // "The main causes of climate change are..."

// 3. Optimize the program
$optimizer = new TextGradientDescentOptimizer();
$evaluator = new DatasetEvaluator($testDataset, new ExactMatchMetric());

$optimizedProgram = $optimizer->optimize(
    program: $program,
    evaluator: $evaluator,
    maxIterations: 10
);

// 4. Use optimized version
$betterResult = $optimizedProgram->execute(new QuestionInput('How does photosynthesis work?'));
```

---

## 2. Core Abstractions (Layer 1)

### `Module` - Abstract Base for All Components

**Role**: Pure function over typed inputs/outputs with explicit composition contract
**Collaborators**: Signature, Prediction, Tracer, child Modules

```php
abstract class Module
{
    // Core execution contract
    abstract public function forward(mixed $input, Tracer $tracer): Prediction;
    
    // Composition contract
    public function children(): iterable<NamedChild>;
    public function withReplacedChild(string $name, Module $newChild): static;
    
    // Convenience methods
    public function execute(mixed $input): Prediction;
    public function signature(): Signature;
    
    // Properties
    protected Signature $signature;
    protected string $description;
}
```

### `CompositeModule` - DAG Module with Children

**Role**: Orchestrate multiple child modules in a program flow
**Collaborators**: Module children, ModuleWalk for introspection

```php
abstract class CompositeModule extends Module
{
    // Child management
    protected array $childModules = [];
    
    public function children(): iterable<NamedChild>;
    public function withReplacedChild(string $name, Module $newChild): static;
    
    // DAG execution helpers
    protected function executeChild(string $name, mixed $input, Tracer $tracer): Prediction;
    protected function executeChildren(array $inputs, Tracer $tracer): array;
}
```

### `PredictModule` - LLM-backed Leaf Module

**Role**: Terminal module that calls LLM with structured outputs
**Collaborators**: Instructor, Tunables, Signature

```php
class PredictModule extends Module implements TunableModule
{
    // Tunables (optimizable parameters)
    protected string $instructions;
    protected array $fewShotExamples;
    protected ?string $systemPrompt;
    
    // Core prediction
    public function forward(mixed $input, Tracer $tracer): Prediction;
    
    // Tunables contract
    public function tunables(): Tunables;
    public function withTunables(Tunables $tunables): static;
    
    // Properties
    protected StructuredOutput $structuredOutput;
    protected Signature $signature;
}
```

---

## 3. Data Flow & Execution (Layer 2)

### `Prediction` - Typed Return Wrapper

**Role**: Uniform return type carrying outputs, reasoning, and metadata
**Collaborators**: DTOs, Usage metrics

```php
class Prediction
{
    public function __construct(
        public readonly mixed $output,     // Main result DTO
        public readonly ?string $reasoning = null,
        public readonly ?Usage $usage = null,
        public readonly array $metadata = []
    ) {}
    
    // Convenience access
    public function get(string $field = null): mixed;
    public function hasField(string $field): bool;
}
```

### `Signature` - I/O Contract Definition

**Role**: Schema contract defining input/output types and semantics
**Collaborators**: Schema package, DTO validation

```php
class Signature
{
    public function __construct(
        public readonly Schema $inputSchema,
        public readonly Schema $outputSchema,
        public readonly string $description = ''
    ) {}
    
    // Schema operations
    public function validateInput(mixed $input): void;
    public function validateOutput(mixed $output): void;
    public function inputNames(): array;
    public function outputNames(): array;
    
    // Serialization
    public function toSignatureString(): string;
    public function hash(): string;
}
```

### `Tracer` - Execution Observability

**Role**: Capture execution traces for debugging and optimization
**Collaborators**: TraceEvent, TraceLog, Usage metrics

```php
class Tracer
{
    protected array $events = [];
    protected array $pathStack = [];
    
    public function traceModuleCall(string $path, Module $module, mixed $input, Prediction $output): void;
    public function pushPath(string $moduleName): void;
    public function popPath(): void;
    
    // Access
    public function getTrace(): TraceLog;
    public function eventsForPath(string $path): array<TraceEvent>;
}
```

---

## 4. Optimization System (Layer 3)

### `TunableModule` - Interface for Optimizable Modules

**Role**: Contract for modules with optimizable parameters
**Collaborators**: Tunables, Optimizer

```php
interface TunableModule
{
    public function tunables(): Tunables;
    public function withTunables(Tunables $tunables): static;
}
```

### `Tunables` - Optimizable Parameters

**Role**: First-class text parameters (instructions, examples, constraints)
**Collaborators**: Example objects, serialization

```php
class Tunables
{
    public function __construct(
        public string $instructions = '',
        public array $fewShotExamples = [],
        public ?string $style = null,
        public array $constraints = []
    ) {}
    
    // Modification
    public function withInstructions(string $instructions): static;
    public function withExamples(array $examples): static;
    public function withConstraint(string $constraint): static;
    
    // Serialization
    public function toArray(): array;
    public function hash(): string;
}
```

### `TextGradientDescentOptimizer` - Main Optimization Engine

**Role**: Iteratively improve tunables based on feedback
**Collaborators**: Critic, Evaluator, CandidateFactory

```php
class TextGradientDescentOptimizer implements Optimizer
{
    public function optimize(
        Module $program,
        Evaluator $evaluator,
        int $maxIterations = 10
    ): Module;
    
    // Internal optimization loop
    protected function optimizationStep(Module $program, TraceLog $traces, float $currentScore): Module;
    protected function generateCandidates(Module $program, array $critiques): array;
    protected function scoreCandidate(Module $candidate, Evaluator $evaluator): float;
}
```

### `Critic` - Text-as-Gradient Generator

**Role**: Generate improvement suggestions from failed traces
**Collaborators**: TraceEvent, Metric feedback, PredictModule

```php
class Critic
{
    public function critique(
        TraceEvent $event,
        float $metricDelta,
        string $failureContext
    ): Critique;
    
    // Internal critique generation
    protected PredictModule $critiqueGenerator;
}
```

---

## 5. Evaluation & Metrics (Layer 4)

### `Evaluator` - Program Assessment

**Role**: Run program over dataset and compute aggregate scores
**Collaborators**: Dataset, Metric, TraceLog

```php
class DatasetEvaluator implements Evaluator
{
    public function __construct(
        protected Dataset $dataset,
        protected Metric $metric
    ) {}
    
    public function evaluate(Module $program): EvaluationResult;
    
    // Results
    class EvaluationResult {
        public float $score;
        public TraceLog $traces;
        public array $perExampleResults;
    }
}
```

### `Metric` - Task-level Objectives

**Role**: Compute score for output quality
**Collaborators**: Expected vs actual outputs

```php
interface Metric
{
    public function score(mixed $expected, mixed $actual): float;
    public function name(): string;
}

class ExactMatchMetric implements Metric;
class F1Metric implements Metric;
class SemanticSimilarityMetric implements Metric;
```

---

## 6. Composition & Introspection (Layer 5)

### `ModuleWalk` - Program Tree Operations

**Role**: Traverse and modify program structure without reflection
**Collaborators**: Module tree, path-based addressing

```php
class ModuleWalk
{
    public static function enumerate(Module $program): array; // path => Module
    public static function replaceAt(Module $program, string $path, Module $newModule): Module;
    public static function findTunableModules(Module $program): array;
    
    // Path operations
    public static function pathExists(Module $program, string $path): bool;
    public static function getModuleAt(Module $program, string $path): Module;
}
```

### `ProgramSerializer` - Persistence & Versioning

**Role**: Save/load complete program state for reproducibility
**Collaborators**: Module configurations, Tunables, Signature hashes

```php
class ProgramSerializer
{
    public function serialize(Module $program): string;
    public function deserialize(string $serialized): Module;
    
    // Diff and versioning
    public function diff(Module $program1, Module $program2): array;
    public function hash(Module $program): string;
}
```

---

## 7. Data Types & DTOs (Layer 6)

### Input/Output DTOs

**Role**: Strongly-typed data containers for all I/O
**Collaborators**: Schema validation, serialization

```php
// Example DTOs for QA program
readonly class QuestionInput {
    public function __construct(public string $question) {}
}

readonly class AnswerOutput {
    public function __construct(
        public string $answer,
        public float $confidence = 0.0
    ) {}
}

readonly class SubQuestionsOutput {
    public function __construct(public array $subquestions) {}
}
```

### `Example` - Few-shot Training Data

**Role**: Structured example for few-shot learning
**Collaborators**: Tunables, Dataset

```php
class Example
{
    public function __construct(
        public readonly mixed $input,
        public readonly mixed $output,
        public readonly string $reasoning = '',
        public readonly string $source = ''
    ) {}
}
```

---

## 8. Atomic Components & Utilities (Layer 7)

### `TraceEvent` - Single Execution Record

**Role**: Capture single module execution for debugging/optimization
**Collaborators**: Usage, timestamps, serialization

```php
class TraceEvent
{
    public function __construct(
        public readonly string $path,
        public readonly string $moduleName,
        public readonly mixed $input,
        public readonly Prediction $output,
        public readonly Tunables $tunables,
        public readonly Usage $usage,
        public readonly DateTimeImmutable $timestamp
    ) {}
}
```

### `Usage` - Resource Consumption Metrics

**Role**: Track tokens, latency, cost for optimization decisions
**Collaborators**: TraceEvent, optimization budgets

```php
readonly class Usage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public float $latencyMs = 0.0,
        public float $cost = 0.0
    ) {}
    
    public function totalTokens(): int;
    public function add(Usage $other): Usage;
}
```

### `Critique` - Optimization Suggestion

**Role**: Structured improvement recommendation from Critic
**Collaborators**: Tunables updates, CandidateFactory

```php
class Critique
{
    public function __construct(
        public readonly string $rationale,
        public readonly array $suggestedInstructions = [],
        public readonly array $suggestedExamples = [],
        public readonly array $suggestedConstraints = []
    ) {}
}
```

---

## 9. Integration Points

### With Existing Monorepo Packages

- **instructor**: StructuredOutput, Schema validation, DTO generation
- **schema**: JSON Schema definitions, validation logic
- **utils**: Text processing, array operations, Result types
- **pipeline**: Data transformation chains (already used in SignatureFactory)

### Package Structure

```
packages/dspy/
├── src/
│   ├── Core/           # Module, Prediction, Signature, Tracer
│   ├── Modules/        # PredictModule, CompositeModule implementations
│   ├── Optimization/   # Optimizer, Critic, Tunables, Metric
│   ├── Evaluation/     # Evaluator, Dataset, scoring logic
│   ├── Introspection/ # ModuleWalk, ProgramSerializer
│   ├── Data/          # DTOs, Example, Usage, TraceEvent
│   └── Utils/         # Helper functions, factories
├── tests/
└── examples/          # QA program, optimization demos
```

---

## 10. Implementation Phases

### Phase 1: Core Foundation
- Module, Signature, Prediction
- Basic PredictModule with Instructor integration
- Simple execution without optimization

### Phase 2: Composition & Tracing
- CompositeModule, ModuleWalk
- Tracer, TraceEvent, TraceLog
- End-to-end program execution

### Phase 3: Basic Optimization
- Tunables, TunableModule interface
- Simple TextGradientDescentOptimizer
- Basic Critic implementation

### Phase 4: Evaluation System
- Metric implementations
- Dataset handling
- DatasetEvaluator

### Phase 5: Advanced Features
- ProgramSerializer for persistence
- Advanced optimization strategies
- Performance optimizations

---

## 11. Success Criteria

1. **Developer Experience**: Clean, intuitive API matching DSPy patterns
2. **Type Safety**: All I/O strongly typed, no stringly-typed operations  
3. **Integration**: Seamless use of existing monorepo packages
4. **Performance**: Efficient execution with proper concurrency
5. **Optimization**: Working text-gradient-descent with measurable improvements
6. **Observability**: Rich tracing and debugging capabilities
7. **Persistence**: Save/load optimized programs reliably

This plan provides a clear roadmap from high-level developer experience down to atomic components, ensuring each piece has a well-defined role and clear integration points.