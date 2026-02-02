# Plan: instructor-e416 — CanUseTools returns AgentState + delete CanReportObserverState

## Summary

Change `CanUseTools::useTools()` from `(AgentState, Tools, CanExecuteToolCalls): AgentStep` to `(AgentState, Tools): AgentState`. Move executor and observer to driver constructors. Keep `Tools` as a per-call param (tools can be augmented at build time via `UseSubagents`). Drivers merge hook-mutated state internally and return state with step embedded. Delete `CanReportObserverState` side-channel. Simplify `AgentLoop::useTools()`.

## Implementation Steps

### Step 1: Change CanUseTools interface

**File:** `packages/agents/src/Core/Contracts/CanUseTools.php`

```php
interface CanUseTools {
    public function useTools(AgentState $state, Tools $tools): AgentState;
}
```

Remove imports: `AgentStep`, `CanExecuteToolCalls`. Keep `Tools`.

---

### Step 2: Update ToolCallingDriver

**File:** `packages/agents/src/Drivers/ToolCalling/ToolCallingDriver.php`

**Constructor** — add 2 nullable params at end:
- `?CanExecuteToolCalls $executor = null`
- `?HookStackObserver $hookObserver = null`

**Remove from class declaration:** `implements CanReportObserverState`

**Remove fields:** `$inferenceObserver`, `$observerState`

**Remove methods:** `withInferenceObserver()`, `observerState()`

**Add methods:**
- `withExecutor(CanExecuteToolCalls $executor): self` — clone + set
- `withHookObserver(HookStackObserver $hookObserver): self` — clone + set
- `mergeObserverState(AgentState $state): AgentState` — returns `$this->hookObserver?->state() ?? $state`

**Modify `useTools()`:**
```php
public function useTools(AgentState $state, Tools $tools): AgentState {
    $response = $this->getToolCallResponse($state, $tools);
    $state = $this->mergeObserverState($state);           // inference hook state
    $toolCalls = $this->getToolsToCall($response);
    $executions = $this->executor?->executeTools($toolCalls, $state) ?? new ToolExecutions();
    $state = $this->mergeObserverState($state);           // tool hook state
    $messages = $this->formatter->makeExecutionMessages($executions);
    $context = $state->messagesForInference();
    $step = $this->buildStepFromResponse($response, $executions, $messages, $context);
    return $state->withCurrentStep($step);
}
```

**Modify `getToolCallResponse()`:** remove `$this->observerState = ...` assignment (line 146). The method continues to call inference hooks and return the response — state merging happens in `useTools()` via `mergeObserverState()`.

**Modify inference hook methods:** replace `$this->inferenceObserver` with `$this->hookObserver`.

---

### Step 3: Update ReActDriver

**File:** `packages/agents/src/Drivers/ReAct/ReActDriver.php`

Same pattern as ToolCallingDriver:
- Add `$executor`, `$hookObserver` to constructor (nullable)
- Remove `CanReportObserverState`, `$inferenceObserver`, `$observerState`, `storeObserverState()`
- Add `withExecutor()`, `withHookObserver()`, `mergeObserverState()`
- Change signature: `useTools(AgentState $state, Tools $tools): AgentState`
- Replace `$executor->executeTools(...)` with `$this->executor->executeTools(...)`
- Replace `$this->inferenceObserver` with `$this->hookObserver`
- Remove `$this->storeObserverState($state)` calls
- Add `$state = $this->mergeObserverState($state)` after inference hooks + after tool execution
- Every return wraps: `return $state->withCurrentStep($agentStep)`

---

### Step 4: Update DeterministicAgentDriver

**File:** `packages/agents/src/Drivers/Testing/DeterministicAgentDriver.php`

**Constructor** — add `?CanExecuteToolCalls $executor = null` as last param.

**Add:** `withExecutor()` mutator. Update `withSteps()` to preserve executor.

**Modify `useTools()`:**
```php
public function useTools(AgentState $state, Tools $tools): AgentState {
    $scenarioStep = $this->resolveStep();
    $step = match (true) {
        $scenarioStep instanceof ScenarioStep => $this->makeStep($scenarioStep, $state),
        default => $this->defaultStep($state),
    };
    return $state->withCurrentStep($step);
}
```

**Modify `makeStep()`** — remove `CanExecuteToolCalls $executor` param, use `$this->executor`.

---

### Step 5: Delete CanReportObserverState

**Delete:** `packages/agents/src/Core/Contracts/CanReportObserverState.php`

---

### Step 6: Clean ToolExecutor

**File:** `packages/agents/src/Core/Tools/ToolExecutor.php`

- Remove `implements CanReportObserverState`
- Remove `observerState()` method
- Remove import of `CanReportObserverState`
- Keep internal HookStackObserver state propagation in `executeTools()` (between tool calls within a step)

---

### Step 7: Simplify AgentLoop

**File:** `packages/agents/src/Core/AgentLoop.php`

**Simplify `useTools()`:**
```php
private function useTools(AgentState $state): AgentState {
    $state = $state->withHookContextCleared();
    return $this->driver->useTools($state, $this->tools);
}
```

**Delete:** `applyObserverState()` method.

**Remove import:** `CanReportObserverState`.

**`with()` method** — no changes needed. `with(tools:)` updates `$this->tools` which is passed to the driver per-call. Tools propagation just works.

---

### Step 8: Reorder AgentBuilder::build()

**File:** `packages/agents/src/AgentBuilder/AgentBuilder.php`

New construction order:
1. `$eventEmitter` — shared event emitter
2. `$observer` — HookStackObserver (needs hookStack + eventEmitter)
3. `$toolExecutor` — ToolExecutor (needs tools + eventEmitter + observer)
4. `$driver` — via `buildDriver($eventEmitter, $toolExecutor, $observer)`
5. `$errorHandler`
6. `$agent` — AgentLoop (tools, toolExecutor, errorHandler, driver, eventEmitter, observer)

**Remove** the `withInferenceObserver` injection block.

**Modify `buildDriver()`** — add `CanExecuteToolCalls $executor` and `HookStackObserver $observer` params:

For custom drivers (`$this->driver !== null`): inject via `method_exists` checks for `withExecutor`, `withHookObserver`, `withEventEmitter`.

For default: construct `ToolCallingDriver(llm:, retryPolicy:, eventEmitter:, executor:, hookObserver:)`.

---

### Step 9: Update Zero/AgentLoop

**File:** `packages/agents/src/Zero/AgentLoop.php`

Line 54: `$this->driver->useTools($state, $this->tools)` — already has 2 args, matches new signature. Just handle return type change:
```php
$state = $this->driver->useTools($state, $this->tools);
// Remove: $state = $state->withAddedStep($step); (empty stub, step now on state)
```

---

### Step 10: Update tests

**6 files with anonymous CanUseTools implementations:**

Pattern change:
```php
// Before
public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep {
    return new AgentStep();
}
// After
public function useTools(AgentState $state, Tools $tools): AgentState {
    return $state->withCurrentStep(new AgentStep());
}
```

Files:
- `tests/Unit/Agent/AgentLoopStepNumberTest.php`
- `tests/Unit/Agent/AgentContinuationEvaluationFailureTest.php`
- `tests/Unit/Agent/AgentStopExceptionTest.php` (throws, signature only)
- `tests/Unit/Agent/AgentFailureUsageAccumulationTest.php` (throws, signature only)
- `tests/Unit/Agent/AgentDeterministicExecutionTest.php`
- `tests/Unit/Agent/AgentStepStartTimingTest.php`

Remove unused `CanExecuteToolCalls` imports.

**1 file with direct driver call:**
- `tests/Unit/Agent/AgentInferenceContextTest.php` — change `$driver->useTools($state, new Tools(), new ToolExecutor(new Tools()))` to `$driver->useTools($state, new Tools())`, extract step from returned state.

**12+ capability tests via AgentBuilder** — no changes needed.

---

## Verification

```bash
# Full agent test suite
vendor/bin/pest packages/agents/tests/

# No stale references
grep -r 'CanReportObserverState\|applyObserverState\|observerState()' packages/agents/src/
grep -r 'CanExecuteToolCalls \$executor' packages/agents/src/Core/Contracts/CanUseTools.php
```
