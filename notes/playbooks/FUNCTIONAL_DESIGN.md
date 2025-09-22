# Functional Design = Composable Behaviours, Predictable Flow

*Subtitle: Applying functional thinking inside Instructor PHP and the agentic toolkit*

---

## 1) Model behaviours, not structures

*Subtitle: Let interfaces describe intent*

* Treat inputs/outputs as **capabilities** (`ExtractsStructuredData`, `CanProcessState`) instead of leaking raw arrays or DTO internals.
* When structural knowledge is unavoidable, expose it via dedicated interfaces (e.g., `CanCarryState`, `ContinuationCriteria`) so callers depend on contracts, not storage details.
* Derive domain behaviour as pure functions operating on immutable snapshots; defer side-effect wiring to adapters.

---

## 2) Immutability first, everywhere

*Subtitle: State transitions > state mutation*

* Prefer readonly classes (`StepByStep` in `packages/addons/src/StepByStep/StepByStep.php:8`) and value objects that return **new** instances on change.
* In pipelines, treat each step as `State -> State`. Avoid mutating shared registries—have each operation return an updated state instance (see `Pipeline::process()` in `packages/pipeline/src/Pipeline.php:27`).
* Store execution context (messages, token usage, tool invocations) in immutable aggregates; provide `withX()` helpers for updates.

**Before**

```php
$context->messages[] = $newMessage;
$context->toolResult = $tool->execute($params);
```

**After**

```php
$context = $context
    ->withMessages($context->messages()->withAdded($newMessage))
    ->withToolResult($tool->execute($params));
```

---

## 3) Use monadic flows for success + failure

*Subtitle: `Result` keeps happy path clean*

* Wrap effectful steps with `Cognesy\Utils\Result\Result` (`packages/utils/src/Result/Result.php:11`).
* Use `map` for pure transforms, `then` for chaining functions that already return `Result`, and `recover` for fallback logic.
* Push error handling to the edges—`Pipeline` already respects `result()->isSuccess()` as the continuation signal.

**Before**

```php
try {
    $usage = $llm->analyze($input);
    $score = $scorer->score($usage);
    return $score;
} catch (Throwable $e) {
    $logger->error($e->getMessage());
    return null;
}
```

**After**

```php
return Result::from($input)
    ->then(fn ($prompt) => Result::try(fn () => $llm->analyze($prompt)))
    ->map(fn ($usage) => $scorer->score($usage))
    ->recover(fn (Throwable $e) => $fallback->score());
```

---

## 4) Compose processing steps like Lego bricks

*Subtitle: Pipelines and StepByStep are your combinators*

* Structure workflows as sequences of small processors implementing `CanProcessState`. `PipelineBuilder` lets you express “definition” separate from “execution”.
* For agent behaviours, extend `StepByStep` to describe loop conditions (`canContinue()`), next step production, and failure handling without leaking mutation.
* Prefer returning callables from factories so pipelines become reusable recipes.

```php
$buildAgentPipeline = fn (ToolRegistry $tools) => Pipeline::builder()
    ->through($hydratePrompt(...))
    ->through(fn ($state) => $tools->decideTool($state))
    ->through($executeTool(...))
    ->tap($audit(...))
    ->build();

$state = $buildAgentPipeline($registry)->process($initialState);
```

---

## 5) Limit arguments, favour data capsules

*Subtitle: Pass tailored context objects*

* Keep function arity low (≤2 where possible). Bundle related parameters inside immutable context objects.
* Offer `withX()` helpers to evolve contexts without rewriting signatures on every step.
* Align with pipeline style: `fn (AgentState $state) => AgentState` turns long arg lists into composable units.

---

## 6) Leverage currying & partial application

*Subtitle: Pre-configure behaviour for later use*

* Transform multi-argument functions into chained single-argument callables to reduce repetition.
* Use PHP closures to capture configuration and return operations ready for pipelines.

```php
$scoreWith = fn (Scorer $scorer) => fn (Weighting $weights) =>
    fn (AgentState $state): AgentState => $state->withScore($scorer->score($state, $weights));

$scoreAgent = $scoreWith($scorer)($weights);
$pipeline = Pipeline::builder()->through($scoreAgent)->build();
```

Currying enables a library of reusable operations (e.g., `makePromptFormatter($template)` returning `fn (AgentState $state)`), simplifying dynamic agent assembly.

---

## 7) Isolate side effects at the boundary

*Subtitle: Pure core, effectful shell*

* Treat IO (network calls, logging, file writes) as dependencies injected as interfaces. The core returns intentions (`Result<Action>`) that adapters execute.
* In pipelines, wrap effectful steps in `Result::try()` so failures propagate without exceptions escaping randomly.
* For iterative flows (`StepByStep`), keep `makeNextStep()` pure and push actual IO into collaborators passed in via constructor.

---

## 8) Separate definition from execution

*Subtitle: Describe what should happen, then decide when/how*

* `Pipeline::builder()` creates a reusable plan. Only `process()` (or `executeWith()`) runs it, making it easy to test definitions in isolation.
* `StepByStep::iterator()` (`packages/addons/src/StepByStep/StepByStep.php:39`) yields intermediate immutable states—perfect for inspection, replay, or simulation.
* Define agent behaviours as data (`PipelineBuilder`, `StateProcessors`) and execute them in orchestrators. Swap drivers without redefining behaviour.

---

## 9) Build richer parsers than validators

*Subtitle: Interpret, don’t just check*

* For LLM outputs, prefer small decoders that transform raw payloads into domain events or commands. Validation is a side effect of decoding.
* Example: parse tool call JSON into `ToolInvocation` value objects, then feed them through a pipeline; avoid exposing the raw JSON everywhere.
* `Result::then()` chains are ideal for parser combinators—each stage narrows the type.

---

## 10) Managed resources & cleanup

*Subtitle: Functional resource safety in PHP*

* Wrap resource acquisition in objects implementing `__destruct()` or dedicated `close()` methods, but release them via pipelines/finalizers.
* Use `PipelineBuilder::afterEach()` or `->build()->process()` finalizers to ensure cleanup runs even on failure (see `Pipeline::processStack()` finalizers path).
* Think of it as PHP’s version of ZIO’s `Scope`: acquire in one step, release in a later deterministic step.

---

## 11) Test strategy for functional flows

*Subtitle: Deterministic units, property-style behaviours*

* Unit-test each processor/closure independently—given an input state, assert returned state.
* For pipelines, feed fake states and ensure no in-place mutation (`expect($next)->not->toBe($original)` in Pest).
* Mock side-effect adapters only at boundaries; core logic should be pure enough to run with simple fakes.

---

## Do / Don’t

| Do | Don’t |
| --- | --- |
| Express logic as `State -> State` functions | Hide behaviour inside void methods that mutate properties |
| Use `Result::map/then/recover` to encode success/failure | Throw exceptions for expected control flow |
| Curry & partially apply operations for reuse | Repeat configuration in every pipeline step |
| Keep pipelines/StepByStep definitions pure | Mix logging, persistence, and orchestration in one closure |
| Inject side-effect interfaces into adapters | Reach for globals/singletons in core logic |
| Return new immutable instances | Mutate shared collections or arrays in place |

---

## TL;DR

* Think in behaviours: pure functions from state to state, orchestrated via pipelines and step-by-step executors.
* Stick to immutability and `Result`-style monads to handle edge cases without cluttering the happy path.
* Compose small, curried operations into larger agent behaviours, isolating IO and cleanup at the edges.
