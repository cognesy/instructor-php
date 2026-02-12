# Comparison Analysis: Vercel AI SDK vs instructor-php Agent Loop & Hooks

## Executive Summary

**Vercel AI SDK** uses a beautifully simple `do-while` loop with a single compound condition for continuation. **Callbacks don't control flow** - they observe. Stop conditions are composable functions, not evaluation aggregations. This is perhaps the most elegant design pattern of all frameworks analyzed.

---

## Key Differences

### 1. Continuation Control

| Aspect | Vercel AI SDK | instructor-php |
|--------|---------------|---------------|
| **Mechanism** | Compound boolean in do-while | 4-value `ContinuationDecision` with aggregation |
| **Stop conditions** | Composable functions `(steps) => boolean` | Hooks write evaluations |
| **Default** | `stepCountIs(20)` | Complex shouldContinue() |
| **Mental model** | "Continue while work remains" | "Collect votes, aggregate, decide" |

**Vercel AI SDK** - Crystal clear do-while condition:
```typescript
do {
    // ... step execution
} while (
    // Continue if tools were called AND all executed
    (clientToolCalls.length > 0 && clientToolOutputs.length === clientToolCalls.length) &&
    // AND stop condition not met
    !(await isStopConditionMet({ stopConditions, steps }))
);
```

**instructor-php** - Complex multi-step checks:
```php
// Multiple checks scattered through loop
if (!$this->shouldContinue($state)) { ... }
if ($this->isContinuationForbidden($state)) { ... }
$state = $this->aggregateAndClearEvaluations($state);
if ($this->shouldContinue($state)) { ... }
```

### 2. Stop Conditions

**Vercel AI SDK** - Simple composable functions:
```typescript
// Built-in stop conditions
function stepCountIs(n: number): StopCondition {
    return ({ steps }) => steps.length === n;
}

function hasToolCall(toolName: string): StopCondition {
    return ({ steps }) => steps.at(-1)?.toolCalls?.some(tc => tc.toolName === toolName) ?? false;
}

// Custom stop conditions
const highConfidence: StopCondition = ({ steps }) => {
    return steps.at(-1)?.providerMetadata?.confidence > 0.9;
};

// Compose multiple (any can trigger stop)
const agent = new ToolLoopAgent({
    stopWhen: [stepCountIs(10), highConfidence, hasToolCall('finish')],
});
```

**instructor-php** - Hooks write evaluations:
```php
class StepsLimitHook implements Hook {
    public function process(AgentState $state, HookType $event): AgentState {
        if ($state->stepCount() >= $this->maxSteps) {
            return $state->withEvaluation(new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::ForbidContinuation,
                reason: 'Step limit reached',
                stopReason: StopReason::StepsLimitReached,
            ));
        }
        return $state->withEvaluation(
            ContinuationEvaluation::fromDecision(
                self::class,
                ContinuationDecision::AllowStop,
            )
        );
    }
}
```

### 3. Hooks/Callbacks

| Aspect | Vercel AI SDK | instructor-php |
|--------|---------------|---------------|
| **Pattern** | Callbacks (observe only) | Hooks (modify state + control flow) |
| **Flow control** | Stop conditions separate | Hooks write evaluations |
| **Types** | `onStepFinish`, `onFinish` | 8 HookTypes with process() |
| **Return** | void (fire and forget) | AgentState (must return) |

**Vercel AI SDK** - Callbacks observe, don't control:
```typescript
const agent = new ToolLoopAgent({
    // Callback - observation only, no return value affects flow
    onStepFinish: async (step) => {
        console.log(`Step completed: ${step.toolCalls.length} tools`);
        await analytics.log(step);
    },

    // Stop condition - separate concern
    stopWhen: stepCountIs(10),
});
```

**instructor-php** - Hooks control flow via evaluations:
```php
interface Hook {
    public function appliesTo(): array;
    // MUST return state, can write evaluations that affect continuation
    public function process(AgentState $state, HookType $event): AgentState;
}
```

### 4. The Main Loop

**Vercel AI SDK** - Single elegant do-while (~60 lines):
```typescript
do {
    // 1. Prepare step (optional customization)
    const prepareStepResult = await prepareStep?.({ steps, stepNumber, messages });

    // 2. Call model
    const response = await model.doGenerate({ tools, prompt, ... });

    // 3. Parse and execute tools
    const toolCalls = parseToolCalls(response.content);
    clientToolOutputs = await executeTools({ toolCalls, tools });

    // 4. Build step result
    const stepResult = new DefaultStepResult({ ... });
    steps.push(stepResult);

    // 5. Callback (observation only)
    await onStepFinish?.(stepResult);

} while (
    (clientToolCalls.length > 0 && clientToolOutputs.length === clientToolCalls.length) &&
    !(await isStopConditionMet({ stopConditions, steps }))
);
```

**instructor-php** - Complex with multiple exit points (~100+ lines):
```php
while (true) {
    $state = $state->withNewStepExecution();

    if (!$this->shouldContinue($state)) {
        yield $this->onAfterExecution($state);
        return;
    }

    $state = $this->onBeforeStep($state);

    if ($this->isContinuationForbidden($state)) {
        yield $this->onAfterExecution($state);
        return;
    }

    $state = $this->performStep($state);
    $state = $this->onAfterStep($state);
    $state = $this->aggregateAndClearEvaluations($state);
    $state = $this->recordStep($state);

    if ($this->shouldContinue($state)) {
        yield $state;
        continue;
    }

    yield $this->onAfterExecution($state);
    return;
}
```

### 5. Dynamic Configuration

**Vercel AI SDK** - `prepareStep()` for per-step customization:
```typescript
const agent = new ToolLoopAgent({
    prepareStep: async ({ steps, stepNumber }) => {
        // Change model after 5 steps
        if (stepNumber > 5) {
            return { model: openai('gpt-4o-mini') };
        }

        // Limit tools after initial analysis
        if (stepNumber > 0) {
            return { activeTools: ['search', 'summarize'] };
        }

        return undefined;  // Use defaults
    },
});
```

**instructor-php** - Hooks at specific lifecycle points:
```php
// Must implement Hook interface, write to state
class DynamicToolFilterHook implements Hook {
    public function appliesTo(): array {
        return [HookType::BeforeStep];
    }

    public function process(AgentState $state, HookType $event): AgentState {
        // Complex state manipulation
        return $state->withFilteredTools(...);
    }
}
```

---

## Vercel AI SDK Architecture Highlights

### 1. Stop Condition Checker

Simple aggregation - any condition can stop:
```typescript
async function isStopConditionMet({ stopConditions, steps }): Promise<boolean> {
    return (await Promise.all(
        stopConditions.map(condition => condition({ steps }))
    )).some(result => result);
}
```

Compare to instructor-php's `EvaluationProcessor` with priority rules.

### 2. Middleware System (Separate from Flow Control)

Middleware modifies LLM calls, not agent flow:
```typescript
type LanguageModelMiddleware = {
    transformParams?: (options) => CallOptions;  // Modify params
    wrapGenerate?: (options) => Result;          // Wrap non-streaming
    wrapStream?: (options) => StreamResult;      // Wrap streaming
};

// Usage - wraps the model, not the agent loop
const model = wrapLanguageModel({
    model: openai('gpt-4o'),
    middleware: [loggingMiddleware, cachingMiddleware],
});
```

This is fundamentally different from instructor-php hooks that intercept the agent loop itself.

### 3. Clean Separation of Concerns

```
┌─────────────────────────────────────────────────────────┐
│                    Vercel AI SDK                         │
├─────────────────────────────────────────────────────────┤
│ Loop Control:     do-while with compound condition       │
│ Stop Conditions:  Composable (steps) => boolean          │
│ Callbacks:        onStepFinish, onFinish (observe only)  │
│ Customization:    prepareStep, prepareCall               │
│ Model Extension:  Middleware (separate concern)          │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│                    instructor-php                        │
├─────────────────────────────────────────────────────────┤
│ Loop Control:     Multiple shouldContinue checks         │
│ Stop Conditions:  Hooks write ContinuationEvaluation     │
│ Hooks:            8 types, all can affect flow           │
│ Customization:    Also via hooks                         │
│ Everything:       Intertwined via evaluation system      │
└─────────────────────────────────────────────────────────┘
```

---

## Simplification Opportunities for instructor-php

### 1. Replace evaluation aggregation with stop condition functions

**Current:**
```php
// Hooks write evaluations
$state->withEvaluation(new ContinuationEvaluation(
    decision: ContinuationDecision::ForbidContinuation, ...
));

// Complex aggregation
$outcome = ContinuationOutcome::fromEvaluations($evaluations);
return $outcome->shouldContinue();
```

**Simplified (Vercel-style):**
```php
// Simple stop condition functions
$stopConditions = [
    fn($steps) => count($steps) >= $maxSteps,
    fn($steps) => $this->hasToolCall($steps, 'finish'),
    fn($steps) => $this->isConfident($steps),
];

// Simple check
$shouldStop = array_any($stopConditions, fn($condition) => $condition($steps));
```

### 2. Separate callbacks from flow control

**Current:**
```php
interface Hook {
    public function appliesTo(): array;
    public function process(AgentState $state, HookType $event): AgentState;
}
// Hooks MUST write evaluations to influence flow
```

**Simplified (Vercel-style):**
```php
// Callbacks observe, don't control
interface StepCallback {
    public function onStepFinish(StepResult $step): void;
}

// Stop conditions are separate
interface StopCondition {
    public function shouldStop(array $steps): bool;
}
```

### 3. Single compound continuation condition

**Current:**
```php
// Multiple checks at different points
if (!$this->shouldContinue($state)) { ... }
if ($this->isContinuationForbidden($state)) { ... }
if ($this->shouldContinue($state)) { ... }
```

**Simplified (Vercel-style):**
```php
do {
    $step = $this->performStep($state);
    $steps[] = $step;
    $this->callbacks->onStepFinish($step);
} while (
    $this->hasToolCallsToExecute($step) &&
    !$this->isStopConditionMet($steps)
);
```

### 4. Use prepareStep for dynamic configuration

**Current:** Hooks at specific lifecycle points modify state
```php
class DynamicConfigHook implements Hook {
    public function appliesTo(): array {
        return [HookType::BeforeStep];
    }
    public function process(AgentState $state, HookType $event): AgentState {
        // Modify state
    }
}
```

**Simplified (Vercel-style):**
```php
// Single function for step preparation
$prepareStep = function(array $steps, int $stepNumber) {
    if ($stepNumber > 5) {
        return ['model' => $cheaperModel];
    }
    return null;
};
```

### 5. Remove files that become unnecessary

With Vercel-style simplification:
- `ContinuationEvaluation.php` → remove (use stop condition functions)
- `ContinuationOutcome.php` → remove
- `EvaluationProcessor.php` → remove
- `ContinuationDecision.php` → remove
- Most hooks → convert to stop conditions or callbacks

---

## Side-by-Side: Loop Structure

### Vercel AI SDK (~40 lines core logic)
```typescript
do {
    const stepConfig = await prepareStep?.({ steps, stepNumber });
    const response = await model.doGenerate({ ...config, ...stepConfig });

    const toolCalls = parseToolCalls(response);
    const toolOutputs = await executeTools(toolCalls);

    const step = buildStepResult(response, toolCalls, toolOutputs);
    steps.push(step);

    await onStepFinish?.(step);

} while (
    toolCalls.length > 0 &&
    toolOutputs.length === toolCalls.length &&
    !(await isStopConditionMet(stopConditions, steps))
);

await onFinish?.({ steps, totalUsage });
```

### instructor-php Current (~100+ lines)
```php
while (true) {
    $state = $state->withNewStepExecution();
    if (!$this->shouldContinue($state)) { yield ...; return; }

    $state = $this->onBeforeStep($state);
    if ($this->isContinuationForbidden($state)) { yield ...; return; }

    $state = $this->performStep($state);
    $state = $this->onAfterStep($state);
    $state = $this->aggregateAndClearEvaluations($state);
    $state = $this->recordStep($state);
    $this->eventEmitter->stepCompleted($state);

    if ($this->shouldContinue($state)) {
        $state = $state->withClearedCurrentExecution();
        yield $state;
        continue;
    }

    yield $this->onAfterExecution($state);
    return;
}
```

---

## Summary: Key Insights from Vercel AI SDK

| Pattern | Vercel AI SDK | Benefit |
|---------|---------------|---------|
| **Loop condition** | Single compound do-while | One place to understand continuation |
| **Stop conditions** | Composable `(steps) => bool` | Simple, testable, combinable |
| **Callbacks** | Observe only | Clean separation from flow |
| **Customization** | `prepareStep()` function | Per-step config without hooks |
| **Middleware** | Model-level, not loop-level | Separate concerns |
| **Default** | `stepCountIs(20)` | Sensible built-in limit |

**Vercel's philosophy:** The loop condition tells you everything. Stop conditions are simple predicates. Callbacks observe. Middleware is for the model, not the loop.

**Total complexity reduction opportunity:**
- Remove 4-value decision → use boolean stop conditions
- Remove evaluation aggregation → `stopConditions.some(c => c(steps))`
- Separate callbacks from flow control
- Remove ~200 lines of continuation logic
- Clearer mental model: "While tools remain AND not stopped"

---

## Cross-Framework Comparison

| Aspect | Agno | OpenCode | Codex | Vercel AI SDK | instructor-php |
|--------|------|----------|-------|---------------|---------------|
| **Continuation** | RunStatus | finishReason | 3-value result | do-while condition | 4-value + aggregation |
| **Stop conditions** | Status-based | LLM-driven | Result-driven | Composable functions | Hook evaluations |
| **Hooks/Callbacks** | @hook decorator | Input/output mutation | None (events) | Observe only | 8 types, all control flow |
| **Complexity** | Medium | Low | Very Low | **Lowest** | **Highest** |

**The clear winner for simplicity:** Vercel AI SDK's approach of composable stop conditions + observation-only callbacks. It achieves the same functionality with dramatically less code and complexity.

---

## Recommended Simplified instructor-php Design

Based on all four framework analyses:

```php
// Stop conditions (Vercel-style)
$stopConditions = [
    new StepCountCondition(20),
    new HasToolCallCondition('finish'),
    // Custom: fn(array $steps): bool
];

// Main loop (OpenCode/Vercel hybrid)
do {
    $stepConfig = $this->prepareStep($steps, count($steps));
    $response = $this->driver->generate($state, $stepConfig);

    $toolCalls = $response->toolCalls();
    $toolResults = $this->executeTools($toolCalls);

    $step = new StepResult($response, $toolCalls, $toolResults);
    $steps[] = $step;

    // Callbacks - observe only
    foreach ($this->callbacks as $callback) {
        $callback->onStepFinish($step);
    }

    yield $step;

} while (
    count($toolCalls) > 0 &&
    count($toolResults) === count($toolCalls) &&
    !$this->isStopConditionMet($stopConditions, $steps)
);

// Final callback
foreach ($this->callbacks as $callback) {
    $callback->onFinish($steps);
}
```

**Lines of code:** ~30 vs current ~100+
**Files needed:** StopCondition interface + implementations, remove evaluation system entirely
