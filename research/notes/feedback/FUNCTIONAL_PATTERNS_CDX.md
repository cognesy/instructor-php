# Functional Patterns Playbook

## Current State: What the Code Is Reaching For

### StepByStep executor (`packages/addons/src/StepByStep/StepByStep.php`)
- Encapsulates an iterative workflow as successive state transitions. It already hints at functional composition by pushing the work into `makeNextStep`, `updateState`, and `handle*` operations.
- Imperative loops (`while ($this->hasNextStep($state))`) make it harder to reason about termination and side effects. Treat `nextStep` as a pure function `State -> State` returning a new snapshot rather than mutating references.
- Failure handling relies on `try/catch` instead of expressing errors in the type. Replacing `Throwable` branches with `Result<State, Throwable>` (or another discriminated union) would let consumers compose retries or alternative flows without branching.

### Pipeline orchestration (`packages/pipeline/src/Pipeline.php`)
- Tries to implement a middleware chain that can be extended with hooks, middleware, and finalizers. The property stacks, however, are mutable arrays so state sneaks in through side effects.
- The class currently controls execution with nested loops and guards. A functional approach would model a pipeline as a composition of `State -> Result<State>` transformations. Error strategies can be captured as higher-order functions that decide how to join results rather than imperative `if` statements.
- `tryProcess` mixes exception control flow with imperative branching. Prefer `Result::try` or typed errors so each step returns `Result<State, Error>`; the pipeline simply maps and binds over the collection.

### Result monad (`packages/utils/src/Result/Result.php`)
- Provides a basic monadic interface (`map`, `then`, `recover`) but still relies on `Exception` catching and mutable arrays for aggregations. This hints at the desire for expressive error pipelines but stops short of using immutable data and typed errors.
- `map` and `then` coerce any return value back into a `Result`, which is good, yet they collapse `Throwable` into the same path inside a `catch`. Exposing `Result::fromThrowable(callable): Result` would clarify intent and allow more specific handlers.
- Observing how often `Result` is wrapped suggests enriching the API with combinators (`tap`, `ensure`, `mapError`) and collection helpers (`sequence`, `traverse`) to reduce manual branching in callers.

### Store operators (`packages/messages/src/MessageStore/Operators/*`)
- `SectionOperator` and `ParameterOperator` aspire to provide fluent, chainable operations over immutable message stores. They already return new `MessageStore` instances instead of mutating the original, which aligns with functional design.
- The heavy reliance on the underlying `MessageStore` data contract shows the team is still thinking in terms of structures. Turning the store capabilities into interfaces (e.g., `CanProvideSections`, `CanMergeParameters`) would decouple operators from the concrete store implementation and let them compose over behaviors.
- The operators accept broad union types (`array|Message|Messages`), hinting that currying and partial application could yield clearer, single-responsibility functions (e.g., `append(Messages $messages): Closure(MessageStore): MessageStore`).

## Functional Guidelines Tailored to the Codebase

### 1. Default to immutability
- Treat every stateful object (`StepByStep` state, pipeline steps, message stores) as a value object. Any transformation should return a new instance and leave the input untouched.
- Replace mutable collections (`OperatorStack::add`, `OperatorStack::prepend`) with persistent data structures. Provide `withAppended`, `withPrepended` methods that clone internally and return a new stack, then prefer those from callers so pipelines remain referentially transparent.
- When caching or memoization is required, wrap it in dedicated services with explicit interfaces so pure functions stay pure.

### 2. Compose behavior, not structure
- Define interfaces for the behaviors you chain (`CanProcessState`, `CanCarryState`) and work entirely with those interfaces. When a concrete data shape leaks through, extract the behavior into a value object and expose just the methods you need.
- In operators, avoid reaching into collections to manipulate arrays. Provide transformation methods on the collection type (e.g., `Sections::replace`, `Sections::removeByName`) and compose them, so the operator remains a thin orchestrator of behaviors.

### 3. Use monadic flows to simplify branching
- Embrace `Result` (or `Option`) as the default return type for effectful operations. Refactor `Pipeline::tryProcess` to return `Result<CanCarryState, Throwable>` and let callers decide whether to short-circuit, retry, or collect failures.
- Provide combinators that reflect domain operations. Examples: `Result::ensure(callable $predicate, callable $onFailure)`, `Result::tap(callable $sideEffect)`, `Result::mapError(callable $f)`. These keep error handling linear and avoid nested `if` blocks.
- When an operation naturally returns a collection of results, introduce helpers like `Result::sequence(iterable<Result>)` so pipelines can collapse multiple steps into a single success/failure result without manual loops.

### 4. Keep operations small and chainable
- Model each pipeline step as `function (CanCarryState $state): Result<CanCarryState, Error>`; compose them with `then`/`map` or a custom `Pipeline::from(array $steps)` that folds via function composition.
- Replace loops with folds or `array_reduce` equivalents that operate on immutable sequences. Example: build a `PipelineReducer` that takes an initial state and a list of steps, returning the accumulated `Result`.
- Use higher-order functions to wrap cross-cutting concerns. Instead of imperative hook processing, define decorators such as `withHooks(Callable $step, iterable<Callable $hooks>): Callable` that returns a new composed function.

### 5. Limit argument counts via value objects or currying
- When a method needs more than two inputs, introduce value objects that group related parameters (e.g., `PipelineConfig`, `SectionUpdate`). This makes signatures easier to read and enables pattern matching on intent.
- Currying lets you prepare partially applied operations. Example:

```php
/** @return callable(MessageStore): MessageStore */
function appendMessages(Messages $messages): callable {
    return static fn(MessageStore $store) =>
        $store->section($messages->sectionName())
            ->appendMessages($messages);
}
```

Developers can build pipelines such as `Pipeline::from([appendMessages($msgs), setDefaults($defaults)])` without threading the store manually.

### 6. Leverage currying for pipeline configuration
- Provide factory methods that curry parameters: `StepBuilder::withErrorStrategy(ErrorStrategy $strategy): callable(array $steps): Pipeline`. Teams can assemble variants by partially applying the configuration once and reusing the returned function.
- Encourage expressing middleware as curried decorators: `withLogging(Logger $logger): callable(callable $step): callable`. Applying these at pipeline build time keeps runtime execution pure and declarative.
- Currying pairs well with dependency injection. Instead of passing services deep into constructors, expose curried factories that close over the services and return pure transformers.

### 7. Prefer pattern matching over imperative guards
- Use `match` expressions to keep branching flat. For example, in `Pipeline::shouldContinueProcessing`, return a `Result` and handle termination via pattern matching rather than `if`/`return` pairs.
- When dealing with multiple error strategies, define an enum-backed map: `match($this->onError) { ErrorStrategy::FailFast => failFast(), ErrorStrategy::ContinueWithFailure => continueWithFailure(), };`. Each branch returns a function, avoiding runtime `if` statements.

### 8. Treat side effects as explicit boundaries
- Constrain IO, logging, or persistence behind interfaces that return `Result` or other monads. Steps that must perform effects should wrap them in `Result::try` so the pipeline stays pure from the outside.
- Provide dedicated integration layers (e.g., `MessageStoreRepository`) that translate between pure operations and side effects. This keeps domain logic testable and promotes deterministic pipelines.

## Practical Checklist for Developers
- Identify whether the problem is a transformation (`State -> State`) or an effect (`State -> Result`). Reach for immutable return types first.
- If you catch an exception inside domain code, reconsider the boundary. Can the method return `Result` instead? Can the caller decide the recovery pattern?
- When adding a new pipeline step, implement it as a pure function, then add middleware or logging through composed decorators instead of editing the step.
- Before introducing new data fields, ask whether the desired behavior can be expressed by composing existing operations. Prefer to extend interfaces with new behavior methods rather than exposing raw data.
- Keep functions unary where possible. If you need more data, curry or introduce a value object so pipelines remain composable.

Adopting these patterns will make the existing abstractions—`StepByStep`, `Pipeline`, `Result`, and the operator classes—more predictable, testable, and extensible while delivering the functional paradigm the team is aiming for.
