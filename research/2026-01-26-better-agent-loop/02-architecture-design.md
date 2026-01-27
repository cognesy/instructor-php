# Architecture Design: Ephemeral Execution Buffer

**Date:** 2026-01-26

## Core Insight

Use the existing `MessageStore` sections mechanism to separate:
- **Permanent conversation** (`messages` section): user messages + final responses only
- **Ephemeral execution buffer** (`execution_buffer` section): tool traces for current execution

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         AgentState                                   │
│  ┌────────────────────────────────────────────────────────────────┐ │
│  │  MessageStore                                                   │ │
│  │  ├── messages (persistent)                                     │ │
│  │  │   └── user queries + final agent responses                  │ │
│  │  ├── execution_buffer (ephemeral)                              │ │
│  │  │   └── tool_call + tool_result messages                      │ │
│  │  ├── summary (optional)                                        │ │
│  │  └── buffer (optional)                                         │ │
│  └────────────────────────────────────────────────────────────────┘ │
│  ┌────────────────────────────────────────────────────────────────┐ │
│  │  stepExecutions                                                 │ │
│  │  └── Full execution trace with timing/outcomes                 │ │
│  └────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────┘
                                    │
        ┌───────────────────────────┼───────────────────────────────┐
        ▼                           ▼                               ▼
   messagesForInference()      response extraction           debugging/retry
   (messages + buffer)         (messages only)               (stepExecutions)
```

## Component Design

### 1. AgentState Changes

```php
// AgentState.php
final readonly class AgentState
{
    public const DEFAULT_SECTION = 'messages';
    public const EXECUTION_BUFFER_SECTION = 'execution_buffer';  // NEW

    public function messagesForInference(): Messages {
        // Include execution_buffer for LLM context during execution
        return (new SelectedSections([
            'summary',
            'buffer',
            self::DEFAULT_SECTION,
            self::EXECUTION_BUFFER_SECTION,  // NEW
        ]))->compile($this);
    }
}
```

### 2. New Processor: AppendToolTraceToBuffer

```php
// StateProcessing/Processors/AppendToolTraceToBuffer.php
final class AppendToolTraceToBuffer implements CanProcessAgentState
{
    public function canProcess(AgentState $state): bool {
        return $state->currentStep() !== null;
    }

    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = $next ? $next($state) : $state;
        $currentStep = $newState->currentStep();
        if ($currentStep === null) {
            return $newState;
        }

        $toolTraceMessages = $this->extractToolTrace($currentStep->outputMessages());
        if ($toolTraceMessages->isEmpty()) {
            return $newState;
        }

        $store = $newState->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->appendMessages($toolTraceMessages);

        return $newState->withMessageStore($store);
    }

    private function extractToolTrace(Messages $output): Messages {
        return $output->filter(fn(Message $msg) =>
            $msg->role()->value === 'tool' ||
            ($msg->role()->value === 'assistant' && $msg->hasMetadata('tool_calls'))
        );
    }
}
```

### 3. Modified Processor: AppendStepMessages

```php
// StateProcessing/Processors/AppendStepMessages.php (MODIFIED)
final class AppendStepMessages implements CanProcessAgentState
{
    public function __construct(
        private bool $separateToolTrace = false  // NEW: opt-in flag
    ) {}

    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = $next ? $next($state) : $state;
        $currentStep = $newState->currentStep();
        if ($currentStep === null) {
            return $newState;
        }

        $outputMessages = $currentStep->outputMessages();
        if ($outputMessages->isEmpty()) {
            return $newState;
        }

        // NEW: If separating, only append final response
        if ($this->separateToolTrace) {
            $finalResponse = $this->extractFinalResponse($outputMessages);
            if ($finalResponse === null) {
                return $newState;
            }
            return $newState->withMessages(
                $newState->messages()->appendMessage($finalResponse)
            );
        }

        // Legacy: append all (backward compatible)
        return $newState->withMessages(
            $newState->messages()->appendMessages($outputMessages)
        );
    }

    private function extractFinalResponse(Messages $output): ?Message {
        foreach (array_reverse($output->toArray()) as $msgData) {
            $msg = Message::fromArray($msgData);
            if ($msg->role()->value === 'assistant' && !$msg->hasMetadata('tool_calls')) {
                $content = $msg->content();
                if ($content !== '' && $content !== null) {
                    return $msg;
                }
            }
        }
        return null;
    }
}
```

### 4. New Processor: ClearExecutionBuffer

```php
// StateProcessing/Processors/ClearExecutionBuffer.php
final class ClearExecutionBuffer implements CanProcessAgentState
{
    public function canProcess(AgentState $state): bool {
        $outcome = $state->continuationOutcome();
        return $outcome !== null && !$outcome->shouldContinue();
    }

    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = $next ? $next($state) : $state;

        // Only clear if we're actually stopping
        if (!$this->canProcess($newState)) {
            return $newState;
        }

        $store = $newState->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->clear();

        return $newState->withMessageStore($store);
    }
}
```

### 5. AgentBuilder Integration

```php
// AgentBuilder.php
class AgentBuilder
{
    private bool $separateToolTrace = false;

    public function withSeparatedToolTrace(bool $enabled = true): self {
        $this->separateToolTrace = $enabled;
        return $this;
    }

    private function buildProcessors(): StateProcessors {
        $processors = [];

        // ... existing processors ...

        if ($this->separateToolTrace) {
            $processors[] = new AppendToolTraceToBuffer();
            $processors[] = new AppendStepMessages(separateToolTrace: true);
            $processors[] = new ClearExecutionBuffer();
        } else {
            $processors[] = new AppendStepMessages(separateToolTrace: false);
        }

        return new StateProcessors(...$processors);
    }
}
```

## Processing Order

```
performStep()
├─ StateProcessors::apply()
│  ├─ ApplyCachedContext
│  ├─ AppendContextMetadata
│  ├─ [NEW] AppendToolTraceToBuffer  (tool traces → execution_buffer)
│  ├─ AppendStepMessages             (final response → messages)
│  ├─ [NEW] ClearExecutionBuffer     (clear buffer if stopping)
│  └─ Terminal: useTools()
```

## Subagent Integration

No changes needed to subagent tools. They already:
1. Create isolated AgentState for subagent
2. Return only text summary to parent
3. Store full state separately for debugging

The execution buffer approach aligns with this pattern:
- Subagent has its own execution_buffer
- Parent never sees subagent's buffer
- Parent's messages only get subagent's response text

## SOLID Compliance

### Single Responsibility
- `AppendToolTraceToBuffer`: Only handles tool trace → buffer
- `AppendStepMessages`: Only handles response → messages
- `ClearExecutionBuffer`: Only handles buffer cleanup

### Open/Closed
- New processors added, existing code minimally modified
- Behavior controlled by constructor flag (backward compatible)

### Liskov Substitution
- All processors implement `CanProcessAgentState`
- Can be swapped/composed freely

### Interface Segregation
- Processors only depend on `AgentState`
- No unnecessary dependencies

### Dependency Inversion
- Processors depend on abstractions (Messages, MessageStore)
- No concrete driver dependencies
