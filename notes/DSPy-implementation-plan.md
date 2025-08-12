# DSPy-PHP Implementation Plan
*Building from essential mechanisms to complete Module system*

---

## Analysis Summary

### Production Package Quality Assessment
**✅ Excellent Foundations:**
- **Pipeline**: Robust error handling, immutable state, powerful composition (packages/pipeline/src/Pipeline.php:1)
- **Utils/Result**: Type-safe error handling, monadic operations (packages/utils/src/Result/Result.php:1)
- **Instructor**: Mature structured output generation, proper error handling (packages/instructor/src/Core/ResponseGenerator.php:1)
- **Evals**: Complete experiment/observation framework (packages/evals/src/Experiment.php:1)
- **Schema**: JSON Schema validation and type safety
- **Dynamic**: Runtime structure generation from strings

### Experimental Package Reality Check
**❌ Mostly Broken:**
- Autoloading issues, missing dependencies, incomplete implementations
- Only architectural concepts are valuable, not the code
- Need complete rewrite with production-grade standards

### Key Integration Insights
1. **Pipeline as Backbone**: Use for all processing flows (like ResponseGenerator does)
2. **Result Type Everywhere**: Consistent error handling across all operations
3. **Event-Driven Design**: Follow Evals package pattern for observability
4. **Immutable State**: All updates return new instances

---

## Essential Mechanisms (Foundation Layer)

### 1. Core Data Types
**Expected Outcomes:**
- Type-safe value objects for all DSPy primitives
- Immutable state with builder patterns
- Full serialization support

**Testable Mechanisms:**
```php
// Usage metrics
$usage = Usage::create(inputTokens: 100, outputTokens: 50, latencyMs: 250.0);
$combined = $usage->add($otherUsage);
expect($combined->totalTokens())->toBe(200);

// Tunables (optimizable parameters)  
$tunables = Tunables::create(instructions: "Analyze the text")
    ->withExample(new Example($input, $output))
    ->withStyle("concise");
expect($tunables->toPromptParts())->toHaveKey('instructions');

// Prediction wrapper
$prediction = Prediction::create($outputDTO)
    ->withUsage($usage)
    ->withReasoning("Applied sentiment analysis rules");
expect($prediction->output)->toBeInstanceOf(SentimentOutput::class);
```

### 2. Tracing Infrastructure  
**Expected Outcomes:**
- Complete execution observability
- Path-based event collection
- Debug/optimization data capture

**Testable Mechanisms:**
```php
// TraceEvent - single execution record
$event = TraceEvent::create(
    path: "qa.expand",
    input: $questionInput,
    output: $prediction,
    tunables: $moduleTunables
);
expect($event->pathMatches("*.expand"))->toBeTrue();

// Tracer - execution logger
$tracer = new Tracer();
$tracer->enter("expand");
// ... execution ...
$tracer->exit($prediction, $tunables);
expect($tracer->getEvents())->toHaveCount(1);
```

### 3. Module Foundation
**Expected Outcomes:**
- Clean composition API with children management
- Immutable module updates  
- Path-based introspection

**Testable Mechanisms:**
```php
// Base Module with children
abstract class Module {
    public function forward(mixed $input, Tracer $tracer): Result<Prediction>;
    public function children(): array; // name => Module
    public function withChild(string $name, Module $child): static;
    public function execute(mixed $input): Result<Prediction>;
}

// Test composition
$parent = new CompositeModule();
$newParent = $parent->withChild("predictor", new PredictModule(...));
expect($newParent->children())->toHaveKey("predictor");
expect($parent->children())->toBeEmpty(); // immutable
```

---

## Core Execution Layer

### 4. PredictModule - LLM Integration
**Expected Outcomes:**
- Seamless Instructor integration
- Tunable parameter management
- Robust error handling with Result types

**Testable Mechanisms:**
```php
// PredictModule creation and execution
$module = PredictModule::create(
    signature: 'question: string -> answer: string',
    instructions: 'Provide a concise answer'
);

$result = $module->execute(new QuestionInput("What is PHP?"));
expect($result->isSuccess())->toBeTrue();
expect($result->unwrap()->output)->toBeInstanceOf(AnswerOutput::class);

// Tunable updates
$optimized = $module->withTunables(
    $module->tunables()->withInstructions("Be more detailed")
);
expect($optimized->tunables()->instructions)->toContain("detailed");
```

### 5. CompositeModule - Program Orchestra
**Expected Outcomes:**
- Multi-step program execution
- Child module coordination
- Trace collection across execution tree

**Testable Mechanisms:**
```php
class QuestionAnswerProgram extends CompositeModule {
    public function __construct() {
        $this->expand = PredictModule::create('question -> subquestions');
        $this->answer = PredictModule::create('context, question -> answer');
    }
    
    protected function forward($input, $tracer): Result<Prediction> {
        return Pipeline::builder(ErrorStrategy::FailFast)
            ->through(fn($q) => $this->expand->forward($q, $tracer))
            ->through(fn($sub) => $this->searchFacts($sub)) // external service
            ->through(fn($facts) => $this->answer->forward($facts, $tracer))
            ->executeWith($input)
            ->result();
    }
}

// Test end-to-end execution
$program = new QuestionAnswerProgram();
$result = $program->execute(new QuestionInput("How does photosynthesis work?"));
expect($result->isSuccess())->toBeTrue();
```

---

## Optimization System

### 6. Evaluation Framework  
**Expected Outcomes:**
- Dataset-based program assessment
- Multiple metric types (exact match, F1, semantic similarity)
- Batch evaluation with aggregated results

**Testable Mechanisms:**
```php
// Metric implementations
$exactMatch = new ExactMatchMetric();
expect($exactMatch->score("hello", "hello"))->toBe(1.0);
expect($exactMatch->score("hello", "world"))->toBe(0.0);

// Dataset evaluation
$dataset = Dataset::create([
    ['input' => new QuestionInput("2+2"), 'expected' => new AnswerOutput("4")],
    ['input' => new QuestionInput("3+3"), 'expected' => new AnswerOutput("6")],
]);

$evaluator = new DatasetEvaluator($dataset, $exactMatch);
$result = $evaluator->evaluate($program);
expect($result->score)->toBeGreaterThan(0.8);
expect($result->traces)->toHaveCount(2);
```

### 7. Text-Gradient Optimization
**Expected Outcomes:**
- Iterative tunable improvement
- Critique-based parameter updates
- Convergence detection

**Testable Mechanisms:**
```php
// Critic generates improvement suggestions
$critic = new Critic();
$critique = $critic->analyze($traceEvent, $metricDelta = -0.3);
expect($critique->rationale)->toContain("improvement");
expect($critique->suggestedInstructions)->not->toBeEmpty();

// Optimizer runs improvement loop
$optimizer = new TextGradientDescentOptimizer();
$optimized = $optimizer->optimize($program, $evaluator, maxIterations: 5);
expect($optimized)->not->toBe($program); // new instance
```

---

## Implementation Phases & Testable Deliverables

### Phase 1: Foundation (Week 1-2)
**Deliverables:**
- [ ] `Usage`, `Tunables`, `Prediction` value objects with full test coverage
- [ ] `TraceEvent`, `Tracer` with event collection and filtering
- [ ] Enhanced `Module` base class with children management
- [ ] Pipeline integration for all processing flows

**Tests:**
- Unit tests for all value objects (serialization, immutability)
- Tracer event collection and path filtering
- Module composition and immutability guarantees

### Phase 2: Core Execution (Week 3-4)  
**Deliverables:**
- [ ] `PredictModule` with Instructor integration and error handling
- [ ] `CompositeModule` for multi-step programs
- [ ] End-to-end QuestionAnswer demo program
- [ ] Complete trace collection through execution tree

**Tests:**
- PredictModule LLM calls with mocked responses
- CompositeModule orchestration and error propagation
- E2E program execution with real/mocked LLM calls

### Phase 3: Evaluation System (Week 5-6)
**Deliverables:**  
- [ ] `Dataset`, `Metric` implementations (ExactMatch, F1, Semantic)
- [ ] `DatasetEvaluator` with batch processing
- [ ] Integration with existing Evals package patterns
- [ ] Performance benchmarking tools

**Tests:**
- Metric accuracy on known test cases
- Dataset evaluation with various program configurations
- Performance tests with large datasets

### Phase 4: Optimization (Week 7-8)
**Deliverables:**
- [ ] `Critic` for text-gradient generation
- [ ] `TextGradientDescentOptimizer` with convergence detection  
- [ ] `CandidateGenerator` for tunable variations
- [ ] Complete optimization demo on Q&A program

**Tests:**
- Optimization convergence on synthetic problems
- Critique quality assessment
- Before/after performance comparisons

### Phase 5: Production Features (Week 9-10)
**Deliverables:**
- [ ] `ProgramSerializer` for state persistence
- [ ] `ModuleWalk` for introspection without reflection
- [ ] Performance optimizations and caching
- [ ] Documentation and usage examples

**Tests:**
- Serialization round-trip accuracy
- Module traversal and replacement operations
- Performance benchmarks vs baseline implementations

---

## Success Metrics

### Technical Validation
1. **Type Safety**: Zero `mixed` types in public APIs, full PHPStan level 9 compliance
2. **Error Handling**: All operations return `Result` types, no thrown exceptions in normal flow
3. **Performance**: <100ms overhead for simple modules, <1s for complex programs
4. **Memory**: Immutable operations with minimal memory growth
5. **Integration**: Seamless use of existing monorepo packages

### Functional Validation  
1. **Optimization**: Demonstrable 10%+ improvement on Q&A accuracy through tuning
2. **Composition**: Complex programs with 3+ modules working correctly
3. **Tracing**: Complete execution visibility for debugging
4. **Persistence**: Save/load program state with identical behavior
5. **Developer Experience**: Intuitive API requiring minimal DSPy knowledge

### Quality Gates
- [ ] 100% unit test coverage on core classes
- [ ] Integration tests with real LLM calls
- [ ] Performance benchmarks within acceptable ranges
- [ ] Documentation with working examples
- [ ] Code review by domain expert

---

## Package Organization

```
packages/dspy/
├── src/
│   ├── Data/           # Usage, Tunables, Prediction, TraceEvent
│   ├── Core/           # Module, CompositeModule, PredictModule  
│   ├── Tracing/        # Tracer, TraceLog
│   ├── Evaluation/     # DatasetEvaluator, Metrics, Dataset
│   ├── Optimization/   # Optimizer, Critic, CandidateGenerator
│   ├── Introspection/  # ModuleWalk, ProgramSerializer
│   └── Examples/       # QuestionAnswerProgram demo
├── tests/
│   ├── Unit/           # Individual class tests
│   ├── Integration/    # Cross-component tests  
│   └── E2E/           # Full program execution tests
└── docs/
    ├── quickstart.md
    ├── concepts.md
    └── optimization.md
```

This implementation plan builds systematically from proven production patterns in the monorepo, ensuring each component is independently testable while contributing to a cohesive DSPy system that matches the theoretical framework from the notes.