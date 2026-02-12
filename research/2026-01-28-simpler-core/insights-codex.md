# Comparison Analysis: Codex vs instructor-php Agent Loop & Hooks

## Executive Summary

**Codex** (OpenAI's CLI) uses a remarkably clean turn-based loop with a simple result enum for continuation. **No explicit hooks** - just event emission for observability. Stop/continue is driven by a single `SamplingRequestResult` enum. This is perhaps the simplest architecture of all three comparisons.

---

## Key Differences

### 1. Continuation Control

| Aspect | Codex (Rust) | instructor-php |
|--------|--------------|---------------|
| **Mechanism** | `SamplingRequestResult` enum | 4-value `ContinuationDecision` with aggregation |
| **Decision point** | Single match after each request | Multiple hooks write evaluations |
| **Complexity** | ~15 lines of match statement | ~200+ lines across 4 files |
| **Mental model** | "Request result tells you what to do" | "Collect votes, aggregate, decide" |

**Codex** - Simple result enum:
```rust
pub enum SamplingRequestResult {
    EndTurn(Option<String>),    // Stop - turn complete
    ContinueSampling,           // Continue - more work to do
    Compacting,                 // Compact context then continue
}

// In main loop - one simple match
match run_sampling_request(...).await {
    Ok(SamplingRequestResult::EndTurn(response_id)) => {
        return response_id;  // STOP
    }
    Ok(SamplingRequestResult::ContinueSampling) => {
        // CONTINUE - loop continues
    }
    Ok(SamplingRequestResult::Compacting) => {
        compact_conversation(&sess).await;
        // CONTINUE after compaction
    }
    Err(e) => {
        handle_error(&sess, e).await;
        return None;  // STOP on error
    }
}
```

**instructor-php** - Complex aggregation:
```php
// Hooks write evaluations
$state->withEvaluation(new ContinuationEvaluation(
    decision: ContinuationDecision::ForbidContinuation,
    ...
));

// Loop aggregates from multiple sources
$state = $this->aggregateAndClearEvaluations($state);

// Multiple checks needed
if (!$this->shouldContinue($state)) { ... }
if ($this->isContinuationForbidden($state)) { ... }
```

### 2. Hook System

| Aspect | Codex | instructor-php |
|--------|-------|---------------|
| **Hooks** | **None** - events only | 8 hook types with process() |
| **Flow control** | Not via hooks | Hooks write evaluations for flow |
| **Extensibility** | MCP servers, Skills | Hook interface + HookStack |
| **Pattern** | Observer (emit events) | Interceptor (modify state) |

**Codex** - No hooks, just events:
```rust
// Events are EMITTED, not intercepted
sess.notify(EventMsg::ToolCallStart {
    turn_id, call_id, name, arguments
}).await;

// After tool
sess.notify(EventMsg::ToolCallOutput {
    call_id, output
}).await;

// Events are for OBSERVATION, not modification
```

**instructor-php** - Hooks modify state and control flow:
```php
interface Hook {
    public function appliesTo(): array;
    public function process(AgentState $state, HookType $event): AgentState;
}

// Hooks MUST write evaluations to influence flow
class StepsLimitHook implements Hook {
    public function process(AgentState $state, HookType $event): AgentState {
        return $state->withEvaluation(new ContinuationEvaluation(
            decision: ContinuationDecision::ForbidContinuation,
            ...
        ));
    }
}
```

### 3. The Main Loop

**Codex** - ~50 lines, crystal clear:
```rust
pub async fn run_turn(
    sess: Arc<Session>,
    turn_context: Arc<TurnContext>,
    input: Vec<UserInput>,
    cancellation_token: CancellationToken,
) -> Option<String> {
    // Auto-compact if needed
    if should_auto_compact(&token_info, &sess.config) {
        compact_conversation(&sess).await;
    }

    // Emit start event
    sess.notify(EventMsg::TurnStarted { ... }).await;

    // Record user input
    sess.state.lock().await.record_items(&user_items);

    // Main loop
    loop {
        tokio::select! {
            _ = cancellation_token.cancelled() => {
                sess.notify(EventMsg::TurnAborted { ... }).await;
                return None;
            }

            result = run_sampling_request(...) => {
                match result {
                    Ok(SamplingRequestResult::EndTurn(id)) => return id,
                    Ok(SamplingRequestResult::ContinueSampling) => { }
                    Ok(SamplingRequestResult::Compacting) => {
                        compact_conversation(&sess).await;
                    }
                    Err(e) => {
                        handle_error(&sess, e).await;
                        return None;
                    }
                }
            }
        }
    }
}
```

**instructor-php** - ~100+ lines with multiple continuation checks:
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

### 4. Stop Conditions

**Codex** - Determined inside `try_run_sampling_request()`:
```rust
// After processing stream and executing tools...

// If tools were executed, continue
if !tool_calls.is_empty() {
    execute_tool_calls(...).await;
    return Ok(SamplingRequestResult::ContinueSampling);
}

// Otherwise check stop reason
match response.stop_reason {
    StopReason::EndTurn => {
        sess.notify(EventMsg::TurnComplete { ... }).await;
        Ok(SamplingRequestResult::EndTurn(response.id))
    }
    StopReason::MaxTokens => {
        Ok(SamplingRequestResult::Compacting)
    }
    _ => Ok(SamplingRequestResult::ContinueSampling)
}
```

**instructor-php** - Complex 4-value decision with aggregation:
```php
// Resolution priority (from EvaluationProcessor):
// 1. Any ForbidContinuation → false (guard denied)
// 2. Any RequestContinuation → true (work requested)
// 3. Any AllowStop → false (work complete)
// 4. Any AllowContinuation → true (guards permit)
```

### 5. Tool Execution Control

**Codex** - Approval gates (simple oneshot channels):
```rust
// In tool registry dispatch:
if handler.is_mutating(&invocation).await {
    // Wait for approval before executing
    invocation.turn.tool_call_gate.wait_ready().await;
}

// Execute tool
handler.handle(invocation).await
```

**instructor-php** - Hook-based with evaluations:
```php
// PreToolUse hook can block tools
public function onBeforeToolUse(ToolCall $toolCall, AgentState $state): ToolUseDecision {
    $result = $this->hookStack->process($state, HookType::PreToolUse);

    if ($this->hasBlockingEvaluation($result)) {
        return ToolUseDecision::block($reason);
    }

    return ToolUseDecision::proceed($toolCall);
}
```

---

## Codex Architecture Highlights

### 1. Three-Value Result Enum

The entire continuation system boils down to:
```rust
enum SamplingRequestResult {
    EndTurn(Option<String>),  // Done - stop loop
    ContinueSampling,         // More work - continue
    Compacting,               // Need space - compact then continue
}
```

Compare to instructor-php's four decisions + aggregation + stop reasons.

### 2. Events for Observability, Not Control

Codex emits events but they don't control flow:
```rust
// Events notify observers but don't block or modify
EventMsg::TurnStarted { turn_id, model, ... }
EventMsg::ToolCallStart { call_id, name, arguments }
EventMsg::ToolCallOutput { call_id, output }
EventMsg::TurnComplete { turn_id, response_id }
```

This is fundamentally different from instructor-php hooks that:
- Receive state
- Write evaluations
- Influence continuation decisions

### 3. Parallel Tool Execution with Simple Locking

```rust
// RwLock pattern: read for parallel, write for exclusive
let _guard = if supports_parallel {
    Either::Left(lock.read().await)   // Many can run
} else {
    Either::Right(lock.write().await) // One at a time
};
```

### 4. Retry Logic in One Place

```rust
// Simple retry loop in run_sampling_request
let mut attempts = 0;
loop {
    match try_run_sampling_request(...).await {
        Ok(result) => return Ok(result),
        Err(e) if e.is_retryable() && attempts < MAX_RETRIES => {
            attempts += 1;
            tokio::time::sleep(backoff_duration(attempts)).await;
        }
        Err(e) => return Err(e),
    }
}
```

---

## Simplification Opportunities for instructor-php

### 1. Replace 4-value decision with 3-value result

**Current:**
```php
enum ContinuationDecision {
    case ForbidContinuation;
    case AllowContinuation;
    case RequestContinuation;
    case AllowStop;
}
```

**Simplified (Codex-style):**
```php
enum StepResult {
    case EndExecution;      // Done - stop loop
    case ContinueExecution; // More work - continue
    case NeedsCompaction;   // Compact then continue
}
```

### 2. Remove evaluation aggregation

**Current flow:**
```
Hook → writes ContinuationEvaluation
     → AgentLoop aggregates all evaluations
     → ContinuationOutcome produced
     → Multiple checks (shouldContinue, isForbidden)
```

**Simplified (Codex-style):**
```
performStep() → returns StepResult
match result {
    EndExecution → break,
    ContinueExecution → continue,
    NeedsCompaction → { compact(); continue; }
}
```

### 3. Convert hooks to events (observation only)

**Current:**
```php
interface Hook {
    public function appliesTo(): array;
    public function process(AgentState $state, HookType $event): AgentState;
}
```

**Simplified (Codex-style):**
```php
// Events don't modify state or control flow
interface AgentEventListener {
    public function onEvent(AgentEvent $event): void;
}

// Emit events for observation
$this->eventEmitter->emit(new ToolCallStarted($toolCall));
$result = $this->executeTool($toolCall);
$this->eventEmitter->emit(new ToolCallCompleted($toolCall, $result));
```

### 4. Move flow control into the loop

Instead of hooks writing evaluations, handle limits directly:

```php
public function iterate(AgentState $state): iterable {
    while (true) {
        // Direct checks - no hooks
        if ($state->stepCount() >= $this->maxSteps) {
            break; // Stop
        }

        // Emit event (observation only)
        $this->emit(new StepStarted($state));

        // Perform step and get result
        $result = $this->performStep($state);

        // Emit event
        $this->emit(new StepCompleted($state, $result));

        // Simple result check
        match ($result->type) {
            StepResultType::End => break,
            StepResultType::Continue => null,
            StepResultType::Compact => $this->compact($state),
        };

        yield $state;
    }
}
```

### 5. Simplify tool approval

**Current:** Hook-based with evaluations
```php
public function onBeforeToolUse(...): ToolUseDecision {
    $result = $this->hookStack->process($state, HookType::PreToolUse);
    if ($this->hasBlockingEvaluation($result)) {
        return ToolUseDecision::block($reason);
    }
    return ToolUseDecision::proceed($toolCall);
}
```

**Simplified (Codex-style):** Direct approval check
```php
public function executeTool(ToolCall $call): ToolResult {
    if ($this->requiresApproval($call)) {
        $this->waitForApproval($call); // Simple gate
    }
    return $this->toolRegistry->execute($call);
}
```

---

## Side-by-Side: Complete Loop Comparison

### Codex (~40 lines)
```rust
loop {
    tokio::select! {
        _ = cancellation_token.cancelled() => {
            sess.notify(EventMsg::TurnAborted { ... }).await;
            return None;
        }

        result = run_sampling_request(...) => {
            match result {
                Ok(SamplingRequestResult::EndTurn(id)) => return id,
                Ok(SamplingRequestResult::ContinueSampling) => { }
                Ok(SamplingRequestResult::Compacting) => {
                    compact_conversation(&sess).await;
                }
                Err(e) => {
                    handle_error(&sess, e).await;
                    return None;
                }
            }
        }
    }
}
```

### instructor-php Current (~100+ lines)
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

    $this->eventEmitter->stepCompleted($state);

    if ($this->shouldContinue($state)) {
        $state = $state->withClearedCurrentExecution();
        yield $state;
        continue;
    }

    $state = $state->withClearedCurrentExecution();
    yield $this->onAfterExecution($state);
    return;
}
```

---

## Summary: Key Insights from Codex

| Pattern | Codex | Benefit |
|---------|-------|---------|
| **Result enum** | 3 values (End/Continue/Compact) | Simple, exhaustive match |
| **Hooks** | None - events only | No flow control complexity |
| **Limits** | Direct checks in request | No hook overhead |
| **Approval** | Oneshot channel gates | Simple blocking |
| **Retry** | Single retry loop | Centralized logic |
| **Events** | Observe only | Decoupled from control flow |

**Codex's philosophy:** The sampling request tells you what happened. Match on the result. No voting, no aggregation, no evaluation collection.

**Total complexity reduction opportunity:**
- Remove 4-value decision → use 3-value result
- Remove evaluation aggregation → result determines flow
- Convert hooks to events → observation only
- Remove ~200 lines of continuation logic
- Clearer mental model: "Request result drives loop"

---

## Comparison Across All Three Frameworks

| Aspect | Agno | OpenCode | Codex | instructor-php |
|--------|------|----------|-------|---------------|
| **Continuation** | RunStatus enum | finishReason check | 3-value result | 4-value decision + aggregation |
| **Hooks** | @hook decorator | Input/output mutation | None (events) | 8 types with evaluations |
| **Flow control** | Status-based | LLM-driven | Result-driven | Hook-driven aggregation |
| **Complexity** | Medium | Low | Very Low | High |
| **Lines for continuation** | ~30 | ~10 | ~15 | ~200+ |

**The pattern is clear:** All three successful agent frameworks use simple, direct flow control without evaluation aggregation. instructor-php's approach is the most complex by a significant margin.
