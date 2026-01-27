# Implementation Guide: Execution Buffer

**Date:** 2026-01-26

## Prerequisites

Ensure understanding of:
- `MessageStore` sections mechanism (`packages/messages/src/MessageStore/`)
- `StateProcessors` middleware pattern (`packages/agents/src/Agent/StateProcessing/`)
- `AgentBuilder` processor wiring (`packages/agents/src/AgentBuilder/`)

## Step-by-Step Implementation

### Step 1: Add Execution Buffer Constant

**File:** `packages/agents/src/Agent/Data/AgentState.php`

```php
final readonly class AgentState
{
    public const DEFAULT_SECTION = 'messages';
    public const EXECUTION_BUFFER_SECTION = 'execution_buffer';  // ADD THIS

    // ... rest of class
}
```

### Step 2: Create AppendToolTraceToBuffer Processor

**File:** `packages/agents/src/Agent/StateProcessing/Processors/AppendToolTraceToBuffer.php`

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\StateProcessing\Processors;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

final class AppendToolTraceToBuffer implements CanProcessAgentState
{
    #[\Override]
    public function canProcess(AgentState $state): bool {
        return $state->currentStep() !== null;
    }

    #[\Override]
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
        $traceMessages = [];
        foreach ($output->toArray() as $msgData) {
            $role = $msgData['role'] ?? '';
            $hasToolCalls = isset($msgData['metadata']['tool_calls']);

            // Include tool result messages and tool call messages
            if ($role === 'tool' || ($role === 'assistant' && $hasToolCalls)) {
                $traceMessages[] = $msgData;
            }
        }
        return Messages::fromArray($traceMessages);
    }
}
```

### Step 3: Create ClearExecutionBuffer Processor

**File:** `packages/agents/src/Agent/StateProcessing/Processors/ClearExecutionBuffer.php`

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\StateProcessing\Processors;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;

final class ClearExecutionBuffer implements CanProcessAgentState
{
    #[\Override]
    public function canProcess(AgentState $state): bool {
        $outcome = $state->continuationOutcome();
        return $outcome !== null && !$outcome->shouldContinue();
    }

    #[\Override]
    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = $next ? $next($state) : $state;

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

### Step 4: Modify AppendStepMessages

**File:** `packages/agents/src/Agent/StateProcessing/Processors/AppendStepMessages.php`

Add constructor parameter and conditional logic:

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\StateProcessing\Processors;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

final class AppendStepMessages implements CanProcessAgentState
{
    public function __construct(
        private bool $separateToolTrace = false
    ) {}

    #[\Override]
    public function canProcess(AgentState $state): bool {
        return true;
    }

    #[\Override]
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

        if ($this->separateToolTrace) {
            return $this->appendFinalResponseOnly($newState, $outputMessages);
        }

        return $newState->withMessages(
            $newState->messages()->appendMessages($outputMessages)
        );
    }

    private function appendFinalResponseOnly(AgentState $state, Messages $output): AgentState {
        $finalResponse = $this->extractFinalResponse($output);
        if ($finalResponse === null) {
            return $state;
        }

        return $state->withMessages(
            $state->messages()->appendMessage($finalResponse)
        );
    }

    private function extractFinalResponse(Messages $output): ?Message {
        $messages = $output->toArray();
        foreach (array_reverse($messages) as $msgData) {
            $role = $msgData['role'] ?? '';
            $hasToolCalls = isset($msgData['metadata']['tool_calls']);
            $content = $msgData['content'] ?? '';

            if ($role === 'assistant' && !$hasToolCalls && $content !== '') {
                return Message::fromArray($msgData);
            }
        }
        return null;
    }
}
```

### Step 5: Update messagesForInference()

**File:** `packages/agents/src/Agent/Data/AgentState.php`

```php
public function messagesForInference(): Messages {
    return (new SelectedSections([
        'summary',
        'buffer',
        self::DEFAULT_SECTION,
        self::EXECUTION_BUFFER_SECTION,  // ADD THIS
    ]))->compile($this);
}
```

### Step 6: Add Builder Method

**File:** `packages/agents/src/AgentBuilder/AgentBuilder.php`

Add property and method:

```php
private bool $separateToolTrace = false;

public function withSeparatedToolTrace(bool $enabled = true): self {
    $this->separateToolTrace = $enabled;
    return $this;
}
```

Update processor building logic to use the flag when constructing processors.

## Usage Example

```php
$agent = (new AgentBuilder())
    ->withSeparatedToolTrace(true)  // Enable clean conversation
    ->build();

$state = AgentState::empty()
    ->withMessages(Messages::fromString('What is the weather in Paris?', 'user'));

$finalState = $agent->execute($state);

// messages() contains only: user query + final response
// No tool_call or tool_result messages
echo $finalState->messages()->toString();
```

## Testing Checklist

1. **Basic execution**: Tool calls work, response extracted correctly
2. **Multiple tools**: All traces buffered, only final response in messages
3. **No tool calls**: Regular response flows correctly
4. **Subagent**: Parent only sees subagent response text
5. **Execution interrupted**: Buffer cleared, state consistent
6. **Backward compatible**: Default behavior unchanged

## Migration Notes

- Default behavior unchanged (backward compatible)
- Opt-in via `withSeparatedToolTrace(true)`
- Consider making it default in future major version
