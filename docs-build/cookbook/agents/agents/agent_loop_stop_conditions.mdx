---
title: 'Agent Stop Conditions'
docname: 'agent_loop_stop_conditions'
order: 7
id: '368a'
---
## Overview

The agent loop stops when `AgentState::shouldStop()` returns true. You can trigger stops
from within tools using `AgentStopException`, or apply guard hooks directly
(`StepsLimitHook`, `TokenUsageLimitHook`, `ExecutionTimeLimitHook`).

When a tool stops the agent, the last step is a `ToolExecution` — not a `FinalResponse`.
This means `finalResponse()` returns empty. Use `currentResponse()` to get the best
available output regardless of how the agent stopped:

- `finalResponse()` — strict: only returns text when the LLM completed naturally (no pending tool calls)
- `currentResponse()` — pragmatic: returns `finalResponse()` if available, otherwise the last step's output

Key concepts:
- `AgentStopException`: Throw from a tool to stop the loop immediately
- `StopSignal` / `StopReason`: Describes why the loop stopped
- `finalResponse()` vs `currentResponse()`: Strict vs pragmatic response access
- Guard hooks: step/token/time limits implemented via lifecycle interception

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Continuation\AgentStopException;
use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Events\Support\AgentEventConsoleObserver;
use Cognesy\Agents\Tool\Tools\BaseTool;
use Cognesy\Messages\Messages;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

// A tool that counts up and stops when it reaches a target
class CounterTool extends BaseTool
{
    private static int $count = 0;

    public function __construct(private int $stopAt = 3) {
        parent::__construct(
            name: 'counter',
            description: 'Increments a counter and returns the current value. Call this tool repeatedly.',
        );
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        self::$count++;
        echo "  [Counter] Value: " . self::$count . "\n";

        if (self::$count >= $this->stopAt) {
            // Throw AgentStopException to halt the loop
            throw new AgentStopException(
                signal: new StopSignal(
                    reason: StopReason::StopRequested,
                    message: "Counter reached target: {$this->stopAt}",
                ),
                context: ['final_count' => self::$count],
                source: self::class,
            );
        }

        return "Counter is at " . self::$count . ". Keep going — call counter again.";
    }

    #[\Override]
    public function toToolSchema(): array {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters'),
        )->toArray();
    }
}

$logger = new AgentEventConsoleObserver(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,
    showToolArgs: true,
);

// Create loop with the counter tool
$loop = AgentLoop::default()
    ->withTool(new CounterTool(stopAt: 3))
    ->wiretap($logger->wiretap());

$state = AgentState::empty()->withMessages(
    Messages::fromString('Call the counter tool repeatedly until it stops you.')
);

echo "=== Agent with Stop Condition ===\n\n";
$finalState = $loop->execute($state);

// =========================================================================
// Reading the response after a forced stop
// =========================================================================
//
// When a tool throws AgentStopException, the last step is a ToolExecution
// (the LLM was requesting tool calls when the stop happened). This means:
//
//   finalResponse()   -> empty (no FinalResponse step exists)
//   currentResponse() -> last step's LLM output (best available text)
//
// For stop-exception scenarios the real "answer" is typically in the stop
// signal context or agent metadata — not in the LLM's text output.

echo "\n=== Result ===\n";

// finalResponse() is empty because the agent was stopped mid-tool-execution
$final = $finalState->finalResponse()->toString();
echo "finalResponse():  " . ($final !== '' ? $final : '(empty — agent was stopped, not completed)') . "\n";

// currentResponse() falls back to the last step's output
$current = $finalState->currentResponse()->toString();
echo "currentResponse(): " . ($current !== '' ? $current : '(empty)') . "\n";

// hasFinalResponse() lets you branch on how the agent ended
echo "hasFinalResponse(): " . ($finalState->hasFinalResponse() ? 'true' : 'false') . "\n";

// The stop signal carries the reason and context set by the tool
$stopSignal = $finalState->lastStopSignal();
echo "Stop reason: " . ($stopSignal?->toString() ?? 'unknown') . "\n";
echo "Stop context: " . json_encode($stopSignal?->context ?? []) . "\n";

echo "Steps: {$finalState->stepCount()}\n";
echo "Status: {$finalState->status()->value}\n";

// Assertions
assert($finalState->hasFinalResponse() === false, 'Expected no final response (agent was stopped)');
assert($finalState->lastStopSignal() !== null, 'Expected a stop signal');
assert($finalState->lastStopSignal()->context['final_count'] === 3, 'Expected counter to reach 3');
assert($finalState->stepCount() >= 1, 'Expected at least 1 step');
?>
```
