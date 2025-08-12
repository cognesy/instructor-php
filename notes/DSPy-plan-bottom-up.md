# DSPy MVP Implementation Plan - Bottom-Up

*Building from terminal components to complete composable programs*

## Stage 1: Terminal Value Objects & Data Structures

### 1.1 `Usage` - Resource Consumption Metrics

**Role**: Captures token counts, latency, costs for optimization feedback
**Location**: `packages/experimental/src/Module/Core/Data/Usage.php`
**Collaborators**: Tracer, Prediction
**Integration**: Returned by every Predictor call, aggregated by Tracer

**Key Properties:**
- `tokenCount: TokenCount` - input/output token usage
- `latency: Milliseconds` - execution time
- `cost: Money` - estimated API cost
- `modelId: string` - which model was used

**Key Methods:**
- `merge(Usage $other): Usage` - combine metrics
- `toArray(): array` - for serialization

### 1.2 `Tunables` - Optimizable Text Parameters

**Role**: Container for prompt parameters that can be optimized
**Location**: `packages/experimental/src/Module/Core/Data/Tunables.php`
**Collaborators**: PredictModule, Optimizer, Critic
**Integration**: Embedded in PredictModule, modified by optimization process

**Key Properties:**
- `instructions: string` - LLM instructions (mutable via optimization)
- `fewShot: array<Example>` - example inputs/outputs
- `style: string` - response style hints
- `format: string` - output format constraints

**Key Methods:**
- `withInstructions(string $instructions): self` - immutable update
- `withExamples(array $examples): self` - add few-shot examples
- `toPromptComponents(): array` - render for LLM

### 1.3 `Prediction` - Typed Output Container

**Role**: Uniform return type carrying outputs + metadata
**Location**: `packages/experimental/src/Module/Core/Data/Prediction.php`
**Collaborators**: Module, Tracer, Evaluator
**Integration**: Returned by all Module::forward() calls

**Key Properties:**
- `output: mixed` - the actual result (DTO)
- `reasoning: ?string` - optional chain-of-thought
- `usage: Usage` - resource consumption
- `confidence: ?float` - optional confidence score

**Key Methods:**
- `getOutput(): mixed` - extract main result
- `withReasoning(string $reasoning): self` - add reasoning
- `merge(Prediction $other): self` - combine predictions

## Stage 2: Observability & Tracing

### 2.1 `TraceEvent` - Single Execution Record

**Role**: Immutable record of one module execution
**Location**: `packages/experimental/src/Module/Core/Tracing/TraceEvent.php`
**Collaborators**: Tracer, TraceLog, Optimizer
**Integration**: Created during module execution, collected by Tracer

**Key Properties:**
- `path: string` - module path in execution tree (e.g., "root.expand.predictor")
- `moduleClass: string` - which module executed
- `input: array` - input parameters (serialized)
- `output: Prediction` - result + metadata
- `tunables: Tunables` - snapshot of parameters used
- `timestamp: DateTimeImmutable` - execution time

**Key Methods:**
- `toArray(): array` - for persistence/analysis
- `matches(string $pathPattern): bool` - query helper

### 2.2 `Tracer` - Execution Logger

**Role**: Collects execution traces for analysis/optimization
**Location**: `packages/experimental/src/Module/Core/Tracing/Tracer.php`
**Collaborators**: Module, TraceEvent, Optimizer
**Integration**: Passed to Module::forward(), collects events

**Key Properties:**
- `events: array<TraceEvent>` - execution log
- `currentPath: array<string>` - path stack during execution

**Key Methods:**
- `enter(string $moduleName): void` - start module execution
- `exit(Prediction $result, Tunables $tunables): void` - record completion
- `getEvents(): array<TraceEvent>` - retrieve all events
- `getEventsMatching(string $pathPattern): array` - filtered events

## Stage 3: Core Module Infrastructure

### 3.1 Enhanced `Module` (extends existing)

**Role**: Base class for all composable modules
**Location**: Enhance existing `packages/experimental/src/Module/Core/Module.php`
**Collaborators**: Tracer, Prediction, child Modules
**Integration**: Foundation for all DSPy modules

**New Properties:**
- `children: array<string, Module>` - named child modules for composition

**New Methods:**
- `forward(array $input, Tracer $tracer): Prediction` - main execution (enhanced signature)
- `children(): array<string, Module>` - expose children for traversal
- `withChild(string $name, Module $child): self` - immutable child replacement
- `getChildPaths(): array<string>` - enumerate all paths for optimization

### 3.2 `TunableModule` - Optimizable Base

**Role**: Module that exposes tunables for optimization
**Location**: `packages/experimental/src/Module/Core/TunableModule.php`
**Collaborators**: Module, Tunables, Optimizer
**Integration**: Base for PredictModule and other optimizable modules

**Key Properties:**
- `tunables: Tunables` - optimizable parameters

**Key Methods:**
- `tunables(): Tunables` - expose current tunables
- `withTunables(Tunables $tunables): self` - update parameters
- `renderPrompt(array $input): string` - create LLM prompt from tunables + input

## Stage 4: Prediction Modules

### 4.1 `PredictModule` - LLM Prediction with Tunables

**Role**: Leaf module that calls LLM with optimizable parameters
**Location**: `packages/experimental/src/Module/Modules/PredictModule.php`
**Collaborators**: TunableModule, Instructor, StructuredOutput
**Integration**: Primary building block for LLM-based operations

**Key Properties:**
- `signature: Signature` - I/O contract from existing system
- `structuredOutput: StructuredOutput` - from Instructor
- Inherits `tunables` from TunableModule

**Key Methods:**
- `forward(array $input, Tracer $tracer): Prediction` - LLM inference
- `renderPrompt(array $input): string` - tunables + signature → prompt
- Private: `callLLM(string $prompt): mixed` - actual API call

## Stage 5: Composite Programs

### 5.1 `CompositeModule` - Multi-Step Program

**Role**: Orchestrates multiple child modules in sequence/parallel
**Location**: `packages/experimental/src/Module/Modules/CompositeModule.php`
**Collaborators**: Module, Tracer, child Modules
**Integration**: Foundation for complex multi-step programs

**Key Properties:**
- Inherits `children` from enhanced Module

**Key Methods:**
- `forward(array $input, Tracer $tracer): Prediction` - orchestrate children
- `executeSequence(array $steps, array $input, Tracer $tracer): Prediction` - helper
- `executeFanOut(array $parallel, array $input, Tracer $tracer): array` - parallel helper

## Stage 6: Optimization Infrastructure (Future)

### 6.1 `Evaluator` - Program Assessment

**Role**: Run program over dataset, compute metrics
**Location**: `packages/experimental/src/Module/Optimization/Evaluator.php` 
**Collaborators**: Module, TraceLog, Metric
**Integration**: Used by Optimizer to score candidate programs

**Key Properties:**
- `metric: Metric` - scoring function
- `dataset: array` - evaluation examples

### 6.2 `Critic` - Gradient Generator

**Role**: Generate text-based improvement suggestions
**Location**: `packages/experimental/src/Module/Optimization/Critic.php`
**Collaborators**: TraceEvent, Evaluator, PredictModule
**Integration**: Core of TextGrad optimization loop

## Stage 7: Demo Application

### 7.1 `QuestionAnswerProgram` - End-to-End Demo

**Role**: Demonstrates composition: question → subquestions → search → aggregate → answer
**Location**: `packages/experimental/src/Module/Examples/QuestionAnswerProgram.php`
**Collaborators**: CompositeModule, multiple PredictModules
**Integration**: Showcase complete DSPy workflow

**Program Structure:**
```php
QuestionAnswerProgram (CompositeModule)
├── expand: PredictModule  // question → subquestions
├── search: SearchModule   // subquestions → facts (non-LLM)
├── aggregate: PredictModule // facts → context
└── answer: PredictModule    // context + question → answer
```

**Key Methods:**
- `forward(QuestionInput, Tracer): AnswerPrediction` - main flow
- `expandQuestion()`, `aggregateContext()`, `generateAnswer()` - steps

---

## Implementation Sequence

**Phase 1 (Foundation)**: Usage, Tunables, Prediction, TraceEvent, Tracer
**Phase 2 (Modules)**: Enhanced Module, TunableModule, PredictModule  
**Phase 3 (Composition)**: CompositeModule, QuestionAnswerProgram
**Phase 4 (Demo)**: Working Q&A pipeline with tracing
**Phase 5 (Future)**: Optimization components (Evaluator, Critic, Optimizer)

This bottom-up approach ensures each component is fully testable in isolation while building toward complete composable programs that demonstrate the DSPy paradigm.