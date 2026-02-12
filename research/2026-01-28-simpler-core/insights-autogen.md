# Comparison Analysis: Microsoft AutoGen vs instructor-php Agent Loop & Hooks

## Executive Summary

**AutoGen** uses a fundamentally different architecture: an **async message queue** with explicit termination conditions and minimal **intervention handlers** (just 3 hook points). The termination system uses a simple `TerminationCondition` protocol that returns `StopMessage | None`. This is the cleanest separation of concerns among all frameworks analyzed.

---

## Key Differences

### 1. Architecture Pattern

| Aspect | AutoGen | instructor-php |
|--------|---------|---------------|
| **Pattern** | Async message queue | Imperative while loop |
| **Loop type** | Process one message at a time | Process until stop |
| **Termination** | Explicit `TerminationCondition` protocol | 4-value `ContinuationDecision` aggregation |
| **Mental model** | "Process messages until stop condition" | "Collect votes, aggregate, decide" |

**AutoGen** - Message queue processing:
```python
class RunContext:
    async def _run(self) -> None:
        while True:
            if self._stopped.is_set():
                return
            await self._runtime._process_next()  # One message
```

**instructor-php** - Multiple continuation checks:
```php
while (true) {
    if (!$this->shouldContinue($state)) { ... }
    if ($this->isContinuationForbidden($state)) { ... }
    $state = $this->aggregateAndClearEvaluations($state);
    if ($this->shouldContinue($state)) { ... }
}
```

### 2. Termination Conditions

**AutoGen** - Simple protocol returning `StopMessage | None`:
```python
class TerminationCondition(Protocol):
    async def check(self, messages: List[BaseChatMessage]) -> StopMessage | None:
        """Return StopMessage to terminate, None to continue."""
        ...

# Built-in conditions
class MaxMessageTermination(TerminationCondition):
    async def check(self, messages) -> StopMessage | None:
        self._count += len(messages)
        if self._count >= self._max:
            return StopMessage(content=f"Max messages ({self._max}) reached")
        return None  # Continue

class TextMentionTermination(TerminationCondition):
    async def check(self, messages) -> StopMessage | None:
        for msg in messages:
            if self._text in msg.to_text():
                return StopMessage(content=f"Found '{self._text}'")
        return None  # Continue

# Composable
class OrTermination(TerminationCondition):
    def __init__(self, *conditions):
        self._conditions = conditions

    async def check(self, messages) -> StopMessage | None:
        for condition in self._conditions:
            result = await condition.check(messages)
            if result is not None:
                return result
        return None
```

**instructor-php** - Complex evaluation system:
```php
// Hooks write evaluations
$state->withEvaluation(new ContinuationEvaluation(
    criterionClass: self::class,
    decision: ContinuationDecision::ForbidContinuation,
    reason: 'Step limit reached',
    stopReason: StopReason::StepsLimitReached,
));

// EvaluationProcessor aggregates with priority rules:
// 1. Any ForbidContinuation → false
// 2. Any RequestContinuation → true
// 3. Any AllowStop → false
// 4. Any AllowContinuation → true
```

### 3. Hook System (Intervention Handlers)

| Aspect | AutoGen | instructor-php |
|--------|---------|---------------|
| **Hook points** | 3 (on_send, on_publish, on_response) | 8 HookTypes |
| **Pattern** | Intercept messages before delivery | Process state at lifecycle points |
| **Flow control** | Return `DropMessage` to block | Write evaluations |
| **Purpose** | Security, logging, message modification | Flow control + everything else |

**AutoGen** - Only 3 intervention points:
```python
class InterventionHandler(Protocol):
    async def on_send(self, message, *, message_context, recipient) -> Any | type[DropMessage]:
        """Intercept direct messages. Return DropMessage to block."""
        ...

    async def on_publish(self, message, *, message_context) -> Any | type[DropMessage]:
        """Intercept broadcast messages."""
        ...

    async def on_response(self, message, *, sender, recipient) -> Any | type[DropMessage]:
        """Intercept responses."""
        ...

# Usage in runtime
for handler in self._intervention_handlers:
    temp_message = await handler.on_send(message, ...)
    if temp_message is DropMessage:
        future.set_exception(MessageDroppedException())
        return
    message = temp_message  # Use modified message
```

**instructor-php** - 8 hook types, all can affect flow:
```php
enum HookType {
    case ExecutionStart;
    case ExecutionEnd;
    case BeforeStep;
    case AfterStep;
    case BeforeInference;
    case AfterInference;
    case PreToolUse;
    case PostToolUse;
    case OnError;
}

interface Hook {
    public function appliesTo(): array;
    public function process(AgentState $state, HookType $event): AgentState;
}
```

### 4. Stopping the Runtime

**AutoGen** - Explicit stop calls:
```python
# Stop immediately
await runtime.stop()

# Stop when queue empty
await runtime.stop_when_idle()

# Stop when condition met
await runtime.stop_when(my_condition)

# In team execution - termination condition checked after each message
if termination_condition:
    result = await termination_condition.check(new_messages)
    if result is not None:
        return result  # StopMessage ends team
```

**instructor-php** - Loop checks evaluation outcome:
```php
protected function shouldContinue(AgentState $state): bool {
    if ($state->status() === AgentStatus::Failed) return false;

    $precomputedOutcome = $state->currentExecution()?->continuationOutcome();
    if ($precomputedOutcome !== null) {
        return $precomputedOutcome->shouldContinue();
    }

    $lastOutcome = $state->stepExecutions()->lastOutcome();
    if ($lastOutcome !== null) {
        return $lastOutcome->shouldContinue();
    }

    return $state->stepCount() === 0;
}
```

---

## AutoGen Architecture Highlights

### 1. Separation of Runtime vs Team Termination

AutoGen cleanly separates:
- **Runtime loop** - Processes messages until explicitly stopped
- **Team termination** - TerminationCondition protocol checked after responses

```python
# Runtime level - process messages
class RunContext:
    async def _run(self) -> None:
        while True:
            if self._stopped.is_set():
                return
            await self._runtime._process_next()

# Team level - check termination after each response
class BaseGroupChatManager:
    async def _check_termination(self, messages):
        if self._termination_condition:
            result = await self._termination_condition.check(messages)
            if result is not None:
                await self._publish_stop(result)
```

### 2. Message Envelope Pattern

Clean message wrapping:
```python
@dataclass
class SendMessageEnvelope:
    message: Any                          # Actual payload
    sender: AgentId | None
    recipient: AgentId
    future: Future[Any]                   # For async response
    cancellation_token: CancellationToken
    message_id: str
```

### 3. Type-Based Handler Routing

Simple dispatch by message type:
```python
class RoutedAgent(BaseAgent):
    async def on_message_impl(self, message: Any, ctx: MessageContext) -> Any:
        handlers = self._handlers[type(message)]  # Type lookup
        for handler in handlers:
            if handler.router(message, ctx):      # Filter by decorator
                return await handler(self, message, ctx)
        return await self.on_unhandled_message(message, ctx)
```

---

## Simplification Opportunities for instructor-php

### 1. Replace evaluation system with TerminationCondition protocol

**Current:**
```php
// Complex 4-value enum
enum ContinuationDecision {
    case ForbidContinuation;
    case AllowContinuation;
    case RequestContinuation;
    case AllowStop;
}

// Hooks write evaluations
$state->withEvaluation(new ContinuationEvaluation(...));

// Aggregation logic
ContinuationOutcome::fromEvaluations($evaluations);
```

**Simplified (AutoGen-style):**
```php
interface TerminationCondition {
    public function check(array $steps): ?StopMessage;
}

class MaxStepsTermination implements TerminationCondition {
    public function check(array $steps): ?StopMessage {
        if (count($steps) >= $this->maxSteps) {
            return new StopMessage("Max steps reached");
        }
        return null;  // Continue
    }
}

// In loop - simple check
$stopMessage = $this->terminationCondition->check($steps);
if ($stopMessage !== null) {
    break;
}
```

### 2. Reduce hooks to intervention points

**Current:** 8 hook types, all can write evaluations
```php
enum HookType {
    case ExecutionStart;
    case ExecutionEnd;
    case BeforeStep;
    case AfterStep;
    case BeforeInference;
    case AfterInference;
    case PreToolUse;
    case PostToolUse;
    case OnError;
}
```

**Simplified (AutoGen-style):** 3 intervention points for messages
```php
interface InterventionHandler {
    public function onBeforeInference(Messages $messages): Messages|DropMessage;
    public function onAfterInference(Response $response): Response|DropMessage;
    public function onToolCall(ToolCall $call): ToolCall|DropMessage;
}
```

### 3. Separate termination from hooks entirely

**Current:** Hooks control flow via evaluations
**Simplified:** TerminationCondition is checked separately from hooks

```php
do {
    // Interventions can modify/drop (but not terminate)
    $messages = $this->applyInterventions($messages);

    $step = $this->performStep($state);
    $steps[] = $step;

    // Termination check - completely separate concern
    $stopMessage = $this->checkTermination($steps);
    if ($stopMessage !== null) {
        break;
    }

} while ($step->hasToolCalls());
```

### 4. Use composable termination conditions

**AutoGen-style composition:**
```php
// Compose with Or/And
$termination = new OrTermination(
    new MaxStepsTermination(20),
    new TextMentionTermination("DONE"),
    new HasToolCallTermination("finish"),
);

// Simple check
$stopMessage = $termination->check($steps);
```

---

## Side-by-Side: Termination Logic

### AutoGen (~20 lines total)
```python
# Protocol
class TerminationCondition(Protocol):
    async def check(self, messages) -> StopMessage | None: ...

# Implementation
class MaxMessageTermination:
    async def check(self, messages) -> StopMessage | None:
        self._count += len(messages)
        if self._count >= self._max:
            return StopMessage(content=f"Max ({self._max}) reached")
        return None

# Usage in team
result = await self._termination_condition.check(new_messages)
if result is not None:
    return result  # Done
```

### instructor-php Current (~200+ lines)
```php
// 4-value enum
enum ContinuationDecision { ... }

// Evaluation data class
class ContinuationEvaluation { ... }

// Outcome aggregation
class ContinuationOutcome {
    public static function fromEvaluations(array $evaluations): self { ... }
}

// Processor with priority rules
class EvaluationProcessor {
    public static function shouldContinue(array $evaluations): bool { ... }
    public static function findResolver(array $evaluations, bool $shouldContinue): string { ... }
    public static function determineStopReason(array $evaluations, bool $shouldContinue): StopReason { ... }
}

// Multiple checks in loop
if (!$this->shouldContinue($state)) { ... }
if ($this->isContinuationForbidden($state)) { ... }
$state = $this->aggregateAndClearEvaluations($state);
```

---

## Summary: Key Insights from AutoGen

| Pattern | AutoGen | Benefit |
|---------|---------|---------|
| **Termination** | `check() → StopMessage \| None` | Binary decision, composable |
| **Hooks** | 3 intervention points | Minimal, focused |
| **Separation** | Runtime loop ≠ termination logic | Clean concerns |
| **Composition** | `OrTermination`, `AndTermination` | Combine conditions easily |
| **Message pattern** | Envelopes with futures | Clean async handling |

**AutoGen's philosophy:** The runtime processes messages. Termination conditions decide when to stop. Intervention handlers intercept messages. Each concern is separate.

**Total complexity reduction opportunity:**
- Remove 4-value decision → use `StopMessage | null`
- Remove evaluation aggregation → simple condition check
- Reduce 8 hook types → 3 intervention points
- Remove ~200 lines of continuation logic
- Clearer mental model: "Process until condition returns StopMessage"

---

## Final Cross-Framework Comparison

| Aspect | Agno | OpenCode | Codex | Vercel AI SDK | AutoGen | instructor-php |
|--------|------|----------|-------|---------------|---------|---------------|
| **Termination** | RunStatus | finishReason | 3-value result | stopConditions | TerminationCondition | 4-value + aggregation |
| **Hooks** | @hook | Input/output | None (events) | Callbacks only | 3 interventions | 8 types + evaluations |
| **Pattern** | Status-based | LLM-driven | Result-driven | Composable predicates | Protocol-based | Hook-driven voting |
| **Complexity** | Medium | Low | Very Low | Very Low | **Lowest** | **Highest** |

**Consensus across all frameworks:**
1. Simple binary or ternary stop decisions
2. Hooks/callbacks don't control main flow
3. Termination is a separate concern
4. Composable conditions over evaluation aggregation

---

## Recommended Simplified instructor-php Design

Based on all five framework analyses:

```php
// Termination condition protocol (AutoGen-style)
interface TerminationCondition {
    public function check(array $steps): ?StopMessage;
    public function reset(): void;
}

// Composable conditions
class OrTermination implements TerminationCondition {
    public function __construct(TerminationCondition ...$conditions) { ... }

    public function check(array $steps): ?StopMessage {
        foreach ($this->conditions as $condition) {
            $result = $condition->check($steps);
            if ($result !== null) return $result;
        }
        return null;
    }
}

// Intervention handlers (AutoGen-style, not flow control)
interface InterventionHandler {
    public function onBeforeInference(Messages $messages): Messages;
    public function onToolCall(ToolCall $call): ToolCall|BlockedToolCall;
}

// Main loop (simplified)
do {
    $messages = $this->applyInterventions($messages);
    $step = $this->performStep($state);
    $steps[] = $step;

    foreach ($this->callbacks as $cb) {
        $cb->onStepFinish($step);
    }

    // Termination check - completely separate
    $stopMessage = $this->terminationCondition->check($steps);

} while (
    $stopMessage === null &&
    $step->hasToolCalls()
);

return new ExecutionResult($steps, $stopMessage);
```

**Lines of code:** ~30 vs current ~100+
**Files needed:** TerminationCondition interface + implementations
**Files removed:** ContinuationEvaluation, ContinuationOutcome, EvaluationProcessor, ContinuationDecision
