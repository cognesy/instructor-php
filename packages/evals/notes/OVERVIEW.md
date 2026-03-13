# LLM Evaluations Library - Internal Design Overview

## Purpose and Capabilities

The LLM Evaluations library is a comprehensive framework for systematically testing, measuring, and evaluating LLM-based applications. It enables developers to:

- **Run structured experiments** on LLM systems with configurable test cases
- **Measure performance metrics** including latency, token usage, and correctness
- **Evaluate outputs** using both automated and LLM-based assessment methods
- **Aggregate results** across multiple test runs for statistical analysis
- **Track execution lifecycle** through event-driven architecture

## Core Design Principles

### 1. **Observer Pattern Architecture**
The library is built around a flexible observer pattern that allows pluggable evaluation components:
- **Execution Observers**: Monitor individual test case runs
- **Experiment Observers**: Analyze aggregate experiment results
- **Measurement Observers**: Capture quantitative metrics
- **Evaluation Observers**: Assess qualitative correctness

### 2. **Event-Driven Lifecycle**
All components communicate through events, enabling loose coupling and extensibility:
- `ExecutionDone/Failed/Processed` - Individual test case lifecycle
- `ExperimentStarted/Done` - Overall experiment lifecycle

### 3. **Separation of Concerns**
Clear boundaries between execution, observation, and aggregation responsibilities.

## Key Abstractions and Components

### Core Entities

#### **Execution** (`src/Execution.php`)
Represents a single test case run with its context and results.

**Key Responsibilities:**
- Manages individual test case execution lifecycle
- Stores execution context, timing, and results
- Coordinates observers and data collection
- Handles error states and exceptions

**Key Properties:**
- `id`: Unique execution identifier
- `data`: Execution context and results (`DataMap`)
- `usage`: Token usage statistics
- `timeElapsed`: Execution duration
- `observations`: Generated observations from this execution

#### **Experiment** (`src/Experiment.php`)
Orchestrates multiple executions across a dataset of test cases.

**Key Responsibilities:**
- Manages experiment lifecycle and execution flow
- Coordinates multiple execution instances
- Aggregates results and generates summaries
- Provides experiment-level reporting and display

**Key Properties:**
- `cases`: Test case dataset (Generator)
- `executor`: Execution strategy (`CanRunExecution`)
- `executions`: Collection of individual executions
- `observations`: Experiment-level observations

#### **Observation** (`src/Observation.php`)
Immutable data structure representing a single measurement or evaluation result.

**Key Properties:**
- `type`: Observation category ('metric', 'summary', 'feedback')
- `key`: Measurement identifier
- `value`: Measurement result
- `metadata`: Additional context and parameters

### Core Interfaces

#### **CanRunExecution** (`src/Contracts/CanRunExecution.php`)
Defines execution strategies for different types of LLM operations.

**Implementations:**
- `RunInference`: Standard LLM inference execution
- `RunStructuredOutputInference`: Structured output generation
- `RunModule`: Custom module execution

#### **CanObserveExecution** (`src/Contracts/CanObserveExecution.php`)
Single-observation pattern for execution monitoring.

**Purpose:** Simple 1:1 execution to observation mapping
**Method:** `observe(Execution $execution): Observation`

#### **CanGenerateObservations** (`src/Contracts/CanGenerateObservations.php`)
Multi-observation pattern for complex analysis.

**Purpose:** Generate multiple related observations from single execution
**Method:** `observations(mixed $subject): iterable<Observation>`

#### **CanObserveExperiment** (`src/Contracts/CanObserveExperiment.php`)
Experiment-level observation interface for aggregate analysis.

**Method:** `observe(Experiment $experiment): Observation`

## Component Categories

### 1. **Executors** (`src/Executors/`)
Implement different execution strategies for LLM operations.

**Key Components:**
- `InferenceAdapter`: Handles LLM API communication
- `RunInference`: Standard inference execution
- `RunStructuredOutputInference`: Structured data extraction
- `Data/InferenceData`: Execution configuration and parameters

### 2. **Observers** (`src/Observers/`)
Implement measurement and evaluation logic.

#### **Measure** (`src/Observers/Measure/`)
Quantitative metrics collection:
- `DurationObserver`: Execution timing
- `TokenUsageObserver`: Token consumption tracking

#### **Evaluate** (`src/Observers/Evaluate/`)
Correctness assessment:
- `LLMBooleanCorrectnessEval`: Binary correctness evaluation
- `LLMGradedCorrectnessEval`: Scored correctness evaluation
- `ArrayMatchEval`: Direct comparison evaluation

#### **Aggregate** (`src/Observers/Aggregate/`)
Experiment-level summaries:
- `AggregateExperimentObserver`: Base aggregation logic
- `ExperimentLatency`: Average latency calculation
- `ExperimentFailureRate`: Error rate analysis

### 3. **Events** (`src/Events/`)
Lifecycle event definitions:
- `ExecutionDone/Failed/Processed`: Execution events
- `ExperimentStarted/Done`: Experiment events

### 4. **Utils** (`src/Utils/`)
Supporting utilities:
- `NumberSeriesAggregator`: Statistical aggregation
- `CompareNestedArrays`: Deep comparison logic
- `Combination`: Test case combination generation

## Collaboration Mechanisms

### Execution Flow

1. **Experiment Initialization**
   - Load test cases dataset
   - Configure execution strategy and observers
   - Initialize event dispatcher

2. **Case-by-Case Execution**
   ```
   For each case:
     → Create Execution instance
     → Execute via CanRunExecution strategy
     → Generate observations via observers
     → Store results and handle errors
   ```

3. **Experiment Aggregation**
   - Accumulate usage statistics
   - Generate experiment-level observations
   - Display results and summaries

### Observer Coordination

The library uses a two-phase observation system:

#### **Phase 1: Execution Observers**
- Applied to individual `Execution` instances
- Include default observers (timing, tokens) plus custom ones
- Generate execution-specific metrics and evaluations

#### **Phase 2: Experiment Observers** 
- Applied to complete `Experiment` after all executions
- Aggregate individual observations into summaries
- Generate experiment-level insights

### Data Flow Architecture

```
Test Cases → Execution → Observers → Observations → Aggregation → Summary
     ↓           ↓          ↓            ↓             ↓           ↓
  Dataset    Individual   Measure     Metrics     Statistics   Reports
           Test Runs    & Evaluate   Collection   Calculation
```

### Event-Driven Communication

Events provide decoupled communication between components:
- **Execution Events**: Allow external monitoring of test progress
- **Experiment Events**: Enable integration with external systems
- **Observer Events**: Support custom logging and analysis

## Extension Points

### Custom Observers
Implement observer interfaces to add:
- Domain-specific metrics
- Custom evaluation criteria  
- Specialized aggregation logic

### Custom Executors
Implement `CanRunExecution` for:
- Different LLM providers
- Custom processing pipelines
- Specialized inference modes

### Custom Aggregators
Extend aggregation capabilities:
- Statistical methods
- Custom scoring algorithms
- Multi-dimensional analysis

## Design Benefits

1. **Modularity**: Clear separation enables independent component development
2. **Extensibility**: Interface-based design supports custom implementations
3. **Testability**: Observer pattern facilitates unit testing
4. **Scalability**: Event-driven architecture supports large-scale experiments
5. **Maintainability**: Well-defined abstractions reduce coupling

This architecture provides a robust foundation for comprehensive LLM system evaluation while maintaining flexibility for diverse use cases and requirements.