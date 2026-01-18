# StepByStep mechanism

The StepByStep mechanism provides a foundational architecture for building iterative, state-based processes. It serves as the base for both Chat and ToolUse components, offering a clean template method pattern for step-by-step execution with configurable continuation criteria and state processing.

This document explains the StepByStep mechanism architecture, its components, and how to build custom step-by-step processes.

## Overview

The StepByStep mechanism is designed around these core concepts:

- **Template Method Pattern**: Abstract base class defines the execution flow, concrete implementations provide specific behavior
- **Immutable State Management**: All state objects are immutable, returning new instances on changes
- **Configurable Continuation**: Pluggable criteria determine when to continue or stop execution
- **State Processing Pipeline**: Middleware-style processors transform state after each step
- **Event-Driven Architecture**: Comprehensive event system for observability and extensibility
- **Type Safety**: Strong generic typing ensures type safety across state and step types

## Core Components

### Abstract Base Class

**`StepByStep<TState, TStep>`** - The abstract orchestrator that defines the execution pattern:

```php
use Cognesy\Addons\StepByStep\StepByStep;

abstract class MyStepByStepProcess extends StepByStep
{
    // Implement required abstract methods
    protected function canContinue(object $state): bool { /* ... */ }
    protected function makeNextStep(object $state): object { /* ... */ }
    protected function updateState(object $nextStep, object $state): object { /* ... */ }
    protected function handleFailure(Throwable $error, object $state): object { /* ... */ }
    protected function handleNoNextStep(object $state): object { /* ... */ }
}
```

### State Management

**State Contracts** - Define capabilities that state objects must implement:

- `HasSteps<TStep>` - State contains a collection of steps
- `HasMessageStore` - State manages message history
- `HasMetadata` - State contains metadata
- `HasUsage` - State tracks usage statistics
- `HasStateInfo` - State contains execution metadata
- `HasVariables` - State manages variables

**State Traits** - Provide reusable implementations:

- `HandlesChatSteps` / `HandlesToolUseSteps` - Step collection management
- `HandlesMessageStore` - Message history management
- `HandlesMetadata` - Metadata management
- `HandlesUsage` - Usage tracking
- `HandlesStateInfo` - State information management

### Step Management

**Step Contracts** - Define capabilities that step objects must implement:

- `HasStepInfo` - Step contains execution metadata
- `HasStepMessages` - Step has input/output messages
- `HasStepUsage` - Step tracks usage statistics
- `HasStepMetadata` - Step contains metadata
- `HasStepErrors` - Step can contain errors
- `HasStepChatCompletion` - Step contains chat completion data
- `HasStepToolCalls` - Step contains tool call information
- `HasStepToolExecutions` - Step contains tool execution results

**Step Traits** - Provide reusable implementations for step functionality.

### Continuation Criteria

**`ContinuationCriteria`** - Collection of criteria that determine when to continue:

```php
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;

$criteria = new ContinuationCriteria(
    new StepsLimit(10, fn(HasSteps $state) => $state->stepCount()),
    new TokenUsageLimit(4000, fn(HasUsage $state) => $state->usage()->total()),
);
```

**Built-in Criteria**:

- `StepsLimit` - Maximum number of steps
- `TokenUsageLimit` - Maximum token consumption
- `ExecutionTimeLimit` - Maximum execution time
- `CumulativeExecutionTimeLimit` - Maximum cumulative execution time
- `ErrorPolicyCriterion` - Configurable error handling and retries
- `FinishReasonCheck` - Stop on specific finish reasons
- `ResponseContentCheck` - Stop based on response content
- `ToolCallPresenceCheck` - Continue only if tool calls present

Legacy (still supported):
- `RetryLimit` - Maximum consecutive failures
- `ErrorPresenceCheck` - Stop on errors

### State Processors

**`StateProcessors`** - Middleware pipeline for state transformation:

```php
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AccumulateTokenUsage;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendStepMessages;

$processors = new StateProcessors(
    new AccumulateTokenUsage(),
    new AppendStepMessages(),
);
```

**Built-in Processors**:

- `AccumulateTokenUsage` - Aggregates usage from steps
- `AppendStepMessages` - Adds step messages to state
- `AppendContextMetadata` - Adds context metadata
- `MoveMessagesToBuffer` - Context window management
- `SummarizeBuffer` - Message summarization
- `GenerateNextStep` - Step generation logic

## API Methods

The StepByStep base class provides four key methods:

### `nextStep(object $state): object`
Executes the next step in the process:

```php
$newState = $stepByStep->nextStep($currentState);
```

### `hasNextStep(object $state): bool`
Checks if there are more steps to execute:

```php
if ($stepByStep->hasNextStep($state)) {
    // More steps available
}
```

### `finalStep(object $state): object`
Executes all remaining steps and returns final state:

```php
$finalState = $stepByStep->finalStep($initialState);
```

### `iterator(object $state): iterable`
Returns an iterator for step-by-step execution:

```php
foreach ($stepByStep->iterator($initialState) as $state) {
    // Process each step
}
```

## Abstract Methods to Implement

When extending StepByStep, you must implement these abstract methods:

### `canContinue(object $state): bool`
Determines if the process should continue:

```php
protected function canContinue(object $state): bool
{
    return $this->continuationCriteria->canContinue($state);
}
```

### `makeNextStep(object $state): object`
Creates the next step:

```php
protected function makeNextStep(object $state): object
{
    // Your step creation logic
    return new MyStep(/* ... */);
}
```

### `updateState(object $nextStep, object $state): object`
Updates state with the new step:

```php
protected function updateState(object $nextStep, object $state): object
{
    $newState = $state
        ->withAddedStep($nextStep)
        ->withCurrentStep($nextStep);
    return $this->processors->apply($newState);
}
```

### `handleFailure(Throwable $error, object $state): object`
Handles execution failures:

```php
protected function handleFailure(Throwable $error, object $state): object
{
    $errorStep = MyStep::failure($error);
    return $this->updateState($errorStep, $state);
}
```

### `handleNoNextStep(object $state): object`
Handles process completion:

```php
protected function handleNoNextStep(object $state): object
{
    // Emit completion events, finalize state, etc.
    return $state;
}
```

## Message Compilation

The mechanism includes a message compilation system for preparing messages for different contexts:

### `CanCompileMessages`
Interface for message compilation:

```php
use Cognesy\Addons\StepByStep\MessageCompilation\CanCompileMessages;

class MyMessageCompiler implements CanCompileMessages
{
    public function compile(object $state): Messages
    {
        // Compile messages from state
        return new Messages(/* ... */);
    }
}
```

**Built-in Compilers**:
- `AllSections` - Compiles all message sections
- `SelectedSections` - Compiles specific sections

## Building Custom StepByStep Processes

Here's a complete example of building a custom step-by-step process:

```php
use Cognesy\Addons\StepByStep\StepByStep;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;

// 1. Define your state class
final readonly class MyState implements HasSteps, HasUsage
{
    use HandlesSteps;
    use HandlesUsage;

    public function __construct(
        private MySteps $steps,
        private MyStep $currentStep,
        private Usage $usage,
    ) {}

    // Implement mutator methods...
}

// 2. Define your step class
final readonly class MyStep implements HasStepInfo, HasStepUsage
{
    use HandlesStepInfo;
    use HandlesStepUsage;

    public function __construct(
        private string $action,
        private mixed $result,
        private Usage $usage,
    ) {}
}

// 3. Create your StepByStep implementation
final readonly class MyProcess extends StepByStep
{
    public function __construct(
        private ContinuationCriteria $continuationCriteria,
        private StateProcessors $processors,
    ) {}

    protected function canContinue(object $state): bool
    {
        assert($state instanceof MyState);
        return $this->continuationCriteria->canContinue($state);
    }

    protected function makeNextStep(object $state): MyStep
    {
        assert($state instanceof MyState);
        // Your step creation logic
        return new MyStep(
            action: 'process',
            result: $this->processAction($state),
            usage: new Usage(/* ... */),
        );
    }

    protected function updateState(object $nextStep, object $state): MyState
    {
        assert($state instanceof MyState);
        assert($nextStep instanceof MyStep);

        $newState = $state
            ->withAddedStep($nextStep)
            ->withCurrentStep($nextStep);
        return $this->processors->apply($newState);
    }

    protected function handleFailure(Throwable $error, object $state): MyState
    {
        assert($state instanceof MyState);
        $errorStep = MyStep::failure($error);
        return $this->updateState($errorStep, $state);
    }

    protected function handleNoNextStep(object $state): MyState
    {
        assert($state instanceof MyState);
        // Finalization logic
        return $state;
    }
}

// 4. Use your process
$process = new MyProcess(
    continuationCriteria: new ContinuationCriteria(
        new StepsLimit(5, fn(HasSteps $state) => $state->stepCount()),
        new TokenUsageLimit(1000, fn(HasUsage $state) => $state->usage()->total()),
    ),
    processors: new StateProcessors(
        new AccumulateTokenUsage(),
    ),
);

$initialState = new MyState(/* ... */);
$finalState = $process->finalStep($initialState);
```

## Continuation Criteria Details

### Creating Custom Criteria

Implement `CanDecideToContinue` with the `decide()` method:

```php
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;

class CustomCriteria implements CanDecideToContinue
{
    public function decide(object $state): ContinuationDecision
    {
        if ($this->shouldStop($state)) {
            return ContinuationDecision::ForbidContinuation;
        }
        if ($this->wantsMore($state)) {
            return ContinuationDecision::RequestContinuation;
        }
        return ContinuationDecision::AllowContinuation;
    }
}
```

For observability, also implement `CanExplainContinuation`:

```php
use Cognesy\Addons\StepByStep\Continuation\CanExplainContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

class CustomCriteria implements CanDecideToContinue, CanExplainContinuation
{
    public function decide(object $state): ContinuationDecision { /* ... */ }

    public function explain(object $state): ContinuationEvaluation
    {
        $decision = $this->decide($state);
        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $this->buildReason($state),
            stopReason: $decision === ContinuationDecision::ForbidContinuation
                ? StopReason::GuardForbade
                : null,
            context: ['custom_metric' => $this->metric],
        );
    }
}
```

### ContinuationDecision Enum

Criteria return a `ContinuationDecision` to express their intent:

```php
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;

enum ContinuationDecision: string
{
    case AllowContinuation = 'allow';      // Doesn't oppose continuing
    case ForbidContinuation = 'forbid';    // Vetoes continuation (guards)
    case RequestContinuation = 'request';  // Actively wants to continue
}
```

**Decision priority**:
- If any criterion returns `ForbidContinuation`, the process stops
- If at least one criterion returns `RequestContinuation` and none forbid, process continues
- If all return `AllowContinuation`, the process stops (no active request to continue)

### Criteria Evaluation

Use `evaluate()` for full observability of continuation decisions:

```php
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;

$outcome = $criteria->evaluate($state);

// ContinuationOutcome provides:
$outcome->shouldContinue;   // bool - final decision
$outcome->decision;         // ContinuationDecision enum
$outcome->resolvedBy;       // string - criterion class that decided
$outcome->stopReason;       // StopReason enum
$outcome->evaluations;      // array of ContinuationEvaluation

// Inspect individual criteria results:
foreach ($outcome->evaluations as $eval) {
    echo "{$eval->criterionClass}: {$eval->decision->value} - {$eval->reason}\n";
}
```

### StopReason Enum

When the process stops, `StopReason` provides semantic context:

```php
use Cognesy\Addons\StepByStep\Continuation\StopReason;

enum StopReason: string
{
    case Completed = 'completed';              // Normal completion
    case StepsLimitReached = 'steps_limit';
    case TokenLimitReached = 'token_limit';
    case TimeLimitReached = 'time_limit';
    case RetryLimitReached = 'retry_limit';
    case ErrorForbade = 'error';
    case FinishReasonReceived = 'finish_reason';
    case GuardForbade = 'guard';
    case UserRequested = 'user_requested';
}
```

### Legacy canContinue()

The simple `canContinue()` method still works and delegates to `evaluate()`:

```php
// Simple boolean check
if ($criteria->canContinue($state)) {
    // Continue
}
```

## State Processors Details

### Creating Custom Processors

```php
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;

class CustomProcessor implements CanProcessAnyState
{
    public function canProcess(object $state): bool
    {
        return $state instanceof MyState;
    }

    public function process(object $state, ?callable $next = null): object
    {
        assert($state instanceof MyState);
        // Your processing logic
        $newState = $state->withSomeTransformation();
        return $next ? $next($newState) : $newState;
    }
}
```

### Processor Pipeline

Processors are executed in a middleware chain pattern where each processor can:
- Transform the state
- Call the next processor in the chain
- Short-circuit the chain by not calling `$next`

## Event System Integration

The StepByStep mechanism integrates with the event system for observability:

```php
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Contracts\CanHandleEvents;

class MyProcess extends StepByStep
{
    private CanHandleEvents $events;

    public function __construct(
        // ... other parameters
        ?CanHandleEvents $events = null,
    ) {
        $this->events = EventBusResolver::using($events);
    }

    protected function makeNextStep(object $state): object
    {
        // Emit events
        $this->events->dispatch(new MyStepStarting([
            'step' => $state->stepCount() + 1,
            'state' => $state->toArray(),
        ]));

        // ... step creation logic
    }
}
```

## Collections

The mechanism provides type-safe collections:

### Generic Collections

- `Steps<TStep>` - Base collection for steps
- `Variables` - Collection for variables

### Specific Collections

- `ChatSteps` - Collection of chat steps
- `ToolUseSteps` - Collection of tool use steps

## Exception Handling

The mechanism includes specific exceptions:

- `StepByStepException` - Base exception for the mechanism

## Design Principles

The StepByStep mechanism follows these principles:

1. **Immutability**: All state objects are immutable
2. **Type Safety**: Generic types ensure compile-time type checking
3. **Composability**: Components can be mixed and matched
4. **Extensibility**: Easy to add new criteria, processors, and step types
5. **Separation of Concerns**: Clear boundaries between different responsibilities
6. **Event-Driven**: Observable through comprehensive event system

## Real-World Examples

The mechanism is used by:

- **Chat Component**: Multi-participant conversations with configurable behavior
- **ToolUse Component**: Iterative tool execution with LLM coordination

These implementations demonstrate how to build complex, production-ready systems using the StepByStep mechanism.

## Best Practices

1. **State Design**: Keep state objects focused and implement only necessary contracts
2. **Step Creation**: Make steps self-contained and serializable
3. **Error Handling**: Always implement proper error handling in `handleFailure`
4. **Performance**: Use lazy evaluation and efficient state updates
5. **Testing**: Leverage immutability for easier testing and debugging
6. **Events**: Emit meaningful events for observability
7. **Type Safety**: Use generic types and contracts consistently

The StepByStep mechanism provides a robust foundation for building iterative processes while maintaining clean architecture, type safety, and extensibility.
