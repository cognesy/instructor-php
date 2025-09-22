# Functional Patterns Playbook for instructor-php

This playbook distills practical functional design guidance for this codebase, grounded in what’s already here and working. It builds on these core components:

- Step-by-step executor: `packages/addons/src/StepByStep/StepByStep.php:1`
- Result monad: `packages/utils/src/Result/Result.php:1`
- Pipeline + Operator stack: `packages/pipeline/src/Pipeline.php:1`, `packages/pipeline/src/Internal/OperatorStack.php:1`
- Operators (combinators): `packages/pipeline/src/Operators/...`

The goal: make behavior-first, composable, side-effect-aware code the norm, with simple, chainable building blocks and predictable error handling.

**Key Outcomes**
- Prefer immutable state and stateless operators.
- Model effects and errors with Result (and optionally Option) instead of exceptions.
- Compose behavior with small, unary functions and currying/partial application.
- Keep pipelines declarative; isolate effects at the edges.

**At A Glance**
- Immutability: already embraced via `readonly` classes and state replacement methods.
- Monads: `Result` supports `map`, `then` (bind), and `recover` for happy/edge paths without branching.
- Composition: `OperatorStack` + `CanProcessState` provides a middleware-style functional pipeline.
- Side effects: `Tap`, `TapOnFailure`, and Observation operators separate effects from transformations.

---

**Principles**

- Immutability First
  - Treat all state as immutable snapshots; return new instances on change.
  - Favor `with*` and transformers (e.g., `transform()->...->state()`) over setters.
  - Benefit: simpler reasoning, no hidden coupling, easy parallelization/testing.

- Pure Functions + Stateless Operators
  - Implement `CanProcessState::process` as a pure function of input state (no internal mutation, no hidden I/O).
  - Encapsulate effects in dedicated operators (`Tap*`, Observation) or at the pipeline boundaries.

- Model Outcomes with Result
  - Success/Failure is explicit: branchless flows with `map`, `then`, `recover` in `packages/utils/src/Result/Result.php:1`.
  - Avoid exceptions for control flow; use `Result::failure($e)` and propagate via state.
  - Consider adding Option (Maybe) for nullable cases to avoid “null strategies”.

- Behavior Over Data
  - Program to interfaces that represent behavior: `CanProcessState`, `CanCarryState`, `CanCarryResult`, `HasResult`.
  - Hide concrete data representations behind value objects and these interfaces.
  - Only reach into data when necessary; do it via interfaces or domain-focused value objects.

- Small Arity via Currying/Partial Application
  - Prefer unary functions for composability: `fn(State): State`.
  - Use closures to capture configuration and reduce parameters.
  - Compose by partially applying dependencies up front (e.g., clients, config, constants).

- Compositional Pipelines
  - Build flows from small operators: `Call`, `ConditionalCall`, `FailWhen`, `Tap`, `Finalize`, Observation.
  - Keep the main pipeline declarative; extract complex steps into curried factories.

---

**What’s Already Working**

- Step-by-step executor
  - `packages/addons/src/StepByStep/StepByStep.php:1` is a clean iterative executor with pure transitions:
    - Computes next step via `makeNextStep` and updates via `updateState`.
    - Terminal behavior separated (`handleNoNextStep`, `handleFailure`).
    - Provides `finalStep` and `iterator` for eager/lazy execution.
  - Guidance: keep concrete subclasses pure; no shared mutable members; all step functions should return new state.

- Result monad
  - `packages/utils/src/Result/Result.php:1` exposes `map`, `then` (monadic bind), `recover`, `try`, `tryAll`, `tryUntil`.
  - Patterns:
    - Transform on success: `$result->map(fn($v) => $v2)`
    - Chain dependent ops: `$result->then(fn($v) => doThing($v))`
    - Safe fallback: `$result->recover(fn($e) => $default)`
  - Suggestion: consider catching `Throwable` in `try` (consistency with `tryAll`).
  - Missing combinators worth adding:
    - `ensure(callable $predicate, callable $onFailure)` would guard successful values against domain invariants without leaving the happy path. In the pipeline you could execute `->ensure(fn($state) => $state->result()->isSuccess(), fn() => new ProcessingFailure('Bad state'))` before finalizers instead of manual `if` checks.
    - `tap(callable $sideEffect)` mirrors `Result::ifSuccess` but returns a new `Result`, allowing instrumentation such as `$stepResult->tap(fn($state) => $logger->debug('step ok', ['step' => $stepId]));` inside `Pipeline::process` (`packages/pipeline/src/Pipeline.php:48-55`).
    - `mapError(callable $f)` translates the failure channel while keeping it a failure. `Pipeline::tryProcess` (`packages/pipeline/src/Pipeline.php:126-140`) could return `$result->mapError(fn($error) => new StepFailed($processable, $error));`, giving consumers structured reasons without catching exceptions.
  - Benefits across the codebase:
    - Fewer ad-hoc `ifFailure` / `ifSuccess` branches; pipelines remain linear and expressive.
    - Domain-specific errors (`StepFailed`, `ValidationError`) can be layered without changing success logic.
    - Observability hooks (logging, metrics) stay side-effect-only via `tap`, preserving pure transformation steps.

- Pipeline + Operators
  - `packages/pipeline/src/Pipeline.php:1` models middleware-style functional composition with per-pipeline middleware, per-step hooks, and finalizers.
  - `packages/pipeline/src/Internal/OperatorStack.php:1` builds call stacks functionally (`callStack`).
  - Operators provide useful combinators:
    - Functional calls: `Call` variants (`withState`, `withValue`, `withResult`, `withNoArgs`) and `RawCall`.
    - Flow control: `ConditionalCall`, `Skip`, `Fail`, `FailWhen`, `Terminal`.
    - Effects: `Tap`, `TapOnFailure`, `Finalize`.
    - Observability: `TrackTime`, `TrackMemory`, `StepTiming`, `StepMemory`.
  - State combinators: `TransformState` provides `map`, `when`, `combine`, `addTagsIf*`.

---

**Scenarios → Patterns**

- Transform a successful value, propagate failures
  - Use `Result::map`/`then` or `TransformState::map`.
  - Example:
    - `$state->transform()->map(fn($v) => normalize($v))->state()`
    - `$state->result()->then(fn($v) => compute($v))->ifFailure(fn($e) => log($e));`

- Validation with early failure
  - Prefer `FailWhen::with(fn($s) => !isValid($s), 'Invalid')`.
  - Or use `TransformState::failWhen(fn($v) => isValid($v))` when you validate on value.
  - Keep validation pure and deterministic.

- Branching by condition
  - Use `ConditionalCall` operators:
    - `ConditionalCall::withState(fn($s) => shouldDo($s))->then(Call::withState(doStep(...)))->otherwise(Call::withState(doOther(...)))`
  - Returns unchanged state when no branch applies.

- Side effects (logging/metrics) without altering flow
  - Use `Tap::with*` for success path side effects.
  - Use `TapOnFailure::with(fn($s) => logFailure($s))` to observe failures.
  - Observation operators (`TrackTime`, `TrackMemory`, `StepTiming`, `StepMemory`) add tags without affecting results.

- Finalization regardless of outcome
  - Use `Finalize::with(fn($s) => cleanup($s))` in the pipeline’s finalizers stack.

- Retrying / fallback chains
  - Use `Result::tryUntil($condition, [$a1, $a2], $op1, $op2, ...)` for sequential attempts.
  - Or compose a pipeline that accumulates failures and short-circuits on success using `ErrorStrategy::ContinueWithFailure`.

- Optional/nullable values
  - Prefer an Option/Maybe abstraction over `NullStrategy` when null carries semantic meaning.
  - If sticking with `NullStrategy`, confine null handling to `Call` operators; keep core steps null-free.

- Aggregating tags / metadata
  - Use `TransformState::mergeInto/mergeFrom` to compose tag maps.
  - Add conditional tags via `addTagsIf*` helpers.

---

**Currying and Partial Application in PHP**

- Why: reduce arity; inject dependencies/config once; produce `fn(State): State` operators that compose cleanly.
- How: return closures that capture context.

- Example: curried validator factory
  - `makeValidator = fn(array $rules) => fn(CanCarryState $s) => $s->transform()->map(fn($v) => validate($v, $rules))->state();`
  - Use in pipeline: `Call::withState(makeValidator($rules))`.

- Example: HTTP POST step with captured client and endpoint
  - `httpPost = fn($client) => fn(string $url) => fn(array $payload) => fn(CanCarryState $s) => $s->transform()->map(fn($v) => $client->post($url, $payload))->state();`
  - Use: `Call::withState(httpPost($client)('/endpoint')(['a' => 1]))`.

- Example: domain rule as unary function
  - `applyTax = fn(float $rate) => fn(CanCarryState $s) => $s->transform()->map(fn($v) => withTax($v, $rate))->state();`
  - Compose multiple taxes: `Call::withState(applyTax(0.1)), Call::withState(applyTax(0.05))`.

Guideline: Strive for operators that are all `fn(CanCarryState): CanCarryState`. Wrap external callables with `Call::withState`, `Call::withValue`, or `RawCall` when they already match the shape.

---

**Composition Recipes**

- Build a declarative pipeline
  - Steps (transformations): `Call::withValue(fn($v) => transform($v))`
  - Validation (fail early): `FailWhen::with(fn($s) => !isValid($s), 'Invalid')`
  - Branching: `ConditionalCall::withResult(fn(Result $r) => $r->isSuccess())->then(Call::withState(...))->otherwise(Call::withState(...))`
  - Effects: `Tap::withValue(fn($v) => audit($v))`, `TapOnFailure::with(fn($s) => logError($s))`
  - Observability: `TrackTime::capture('op')`, `TrackMemory::capture('op')`
  - Finalizers: `Finalize::with(fn($s) => release($s))`

- Keep steps stateless and unary; lift data-arg steps into closures via currying.

---

**Concrete Suggestions for This Codebase**

- Result.try consistency
  - In `packages/utils/src/Result/Result.php:1`, `try` catches `Exception` while `tryAll` catches `Throwable`. Prefer catching `Throwable` for symmetry and to avoid engine-error leaks.

- Option/Maybe type
  - Introduce `Option` (Some/None) to model nullable success without overloading `Result` with `null` or adding `NullStrategy`. Interop: `Option::toResult($onNone)`.

- OperatorStack immutability-first API
  - `OperatorStack` already has `with(...)` which clones; prefer it over `add(...)` in builder-style code to reduce accidental mutations. Consider making `add` return a new stack by default, or keep `with` as the public default in high-level builders.

- First-class functional operators
  - Add small operators to reduce ad-hoc closures:
    - `MapValue(fn($v) => ...)`
    - `FilterValue(fn($v) => bool, onFalse: Fail|Skip)`
    - `MapState(fn(State) => State)`
  - These can internally delegate to `Call` but improve readability/composability.

- Composition helpers
  - Provide `compose(CanProcessState ...$ops): CanProcessState` to pre-compose steps for reuse, built on `OperatorStack::callStack`.

- StepByStep convergence with Pipeline
  - Consider adapting StepByStep concrete flows into `CanProcessState` operators when possible. That makes iterative executors pluggable inside pipelines.

---

**Anti-Patterns to Avoid**

- Exceptions for control flow
  - Reserve exceptions for truly exceptional failures; otherwise use `Result::failure` and propagate.

- Shared mutable state
  - Don’t mutate operator members or global singletons during processing. If you must share, thread it explicitly through state.

- Long parameter lists
  - Replace with currying or small config/value objects. Prefer `fn(State): State` by capturing deps.

- Arrays as domain containers
  - Use typed value objects and interfaces; avoid associative arrays for domain data.

---

**Minimal Templates**

- Create a stateless operator from a pure function
  - `Call::withState(fn(CanCarryState $s) => $s->transform()->map(fn($v) => doThing($v))->state())`

- Add a success-only side effect
  - `Tap::withValue(fn($v) => logger()->info('done', ['v' => $v]))`

- Fail when condition met
  - `FailWhen::with(fn($s) => !$s->result()->matches(fn($v) => isValid($v)), 'invalid value')`

- Branching
  - `ConditionalCall::withResult(fn($r) => $r->isSuccess())->then(Call::withState($then))->otherwise(Call::withState($else))`

---

**Adoption Checklist**

- Is the step a pure function of input state? If not, isolate effects.
- Can the step be unary via currying? Reduce arity.
- Are errors modeled with `Result` (or `Option`) instead of exceptions?
- Is the logic expressed as composition of operators rather than imperative control flow?
- Are we programming to behavior interfaces rather than concrete data structures?

Adhering to these patterns will keep pipelines predictable, testable, and easy to extend. Build new features by composing small, curried, stateless operators that speak `Result` and work over immutable state.
