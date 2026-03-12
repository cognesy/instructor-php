---
title: 'Testing Agents'
description: 'Test agents deterministically using FakeAgentDriver and FakeTool without LLM calls'
---

# Testing Agents

The Agents package ships with first-class testing primitives that let you exercise agent behavior without making real LLM calls. By combining `FakeAgentDriver` with `FakeTool`, you can script deterministic scenarios, assert on individual steps, and verify that your agent's tool-calling logic, error handling, and multi-step loops behave exactly as expected.

## FakeAgentDriver

`FakeAgentDriver` replaces a real driver (such as `ToolCallingDriver` or `ReActDriver`) with a scripted sequence of steps. Each step defines what the "LLM" would return -- a final response, a tool call, an intermediate message, or an error. The driver advances through the script one step per loop iteration, giving you full control over the agent's behavior.

### Creating a Driver from Simple Responses

The simplest way to create a fake driver is from one or more string responses. Each string becomes a `FinalResponse` step, meaning the agent loop will stop after consuming it:

```php
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;

// Single response -- the agent loop runs one step and stops
$driver = FakeAgentDriver::fromResponses('Hello!');

// Multiple responses -- each subsequent execute() call consumes the next response
$driver = FakeAgentDriver::fromResponses(
    'First execution result',
    'Second execution result',
);
```

When all scripted steps are exhausted, the driver replays the last step indefinitely. This is useful for agents that may be executed multiple times against the same driver instance.

### Creating a Driver from Scenario Steps

For more sophisticated tests -- especially those involving tool calls -- use `ScenarioStep` objects directly:

```php
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;

$driver = FakeAgentDriver::fromSteps(
    ScenarioStep::toolCall('search', ['query' => 'php'], 'Results found'),
    ScenarioStep::final('Based on the search, here is the answer.'),
);
```

In this example, the first iteration produces a tool call step (the loop continues), and the second iteration produces a final response (the loop stops).

## ScenarioStep Types

`ScenarioStep` is a readonly value object that describes what a single agent loop iteration should produce. Four factory methods cover the common cases:

### `ScenarioStep::final()`

Produces a `FinalResponse` step. The agent loop recognizes this as a terminal response and stops iterating:

```php
ScenarioStep::final('The answer is 42.');
```

### `ScenarioStep::tool()`

Produces a `ToolExecution` step type **without** attaching any tool calls. This is useful for simulating intermediate LLM responses that signal the loop should continue (because the step type is `ToolExecution`), but where no actual tool invocation is needed:

```php
ScenarioStep::tool('Thinking about the problem...');
```

> **Note:** Because no tool calls are attached, this step will not trigger the `ToolExecutor`. If you need actual tool execution, use `ScenarioStep::toolCall()` instead.

### `ScenarioStep::error()`

Produces an `Error` step. The step is created with a `RuntimeException` attached, which the agent loop treats as a failure:

```php
ScenarioStep::error('Something went wrong');
```

### `ScenarioStep::toolCall()`

Produces a `ToolExecution` step **with** a tool call attached. This is the most powerful step type -- it simulates the LLM requesting a specific tool and optionally executes it through the `ToolExecutor`:

```php
ScenarioStep::toolCall(
    toolName: 'bash',
    args: ['command' => 'ls -la'],
    response: '',               // Optional LLM text alongside the tool call
    executeTools: true,          // Whether to actually run the tool (default: true)
);
```

When `executeTools` is `true`, the tool call is forwarded to the `ToolExecutor`, which resolves the tool from the `Tools` collection and invokes it. Set it to `false` to skip execution entirely -- useful when you only need to verify that the correct tool call was produced.

### Custom Usage Tracking

All step factories accept an optional `Usage` parameter for testing token-budget guards or usage reporting:

```php
use Cognesy\Polyglot\Inference\Data\InferenceUsage;

ScenarioStep::final('Done.', usage: new Usage(inputTokens: 100, outputTokens: 50));
ScenarioStep::toolCall('search', ['q' => 'test'], usage: new Usage(200, 80));
```

## FakeTool

`FakeTool` creates tool stubs that implement both `ToolInterface` and `CanDescribeTool`. They can be registered in a `Tools` collection and will be resolved by the `ToolExecutor` when a matching tool call arrives.

### Fixed Return Value

The simplest mock returns the same value regardless of the arguments passed:

```php
use Cognesy\Agents\Tool\Tools\FakeTool;

$tool = FakeTool::returning('search', 'Search the web', 'PHP is great');
```

The three arguments are: tool name, description (used in the tool schema), and the fixed return value.

### Custom Logic

For more realistic stubs, pass a callable that receives the tool's arguments and returns a result:

```php
$tool = new FakeTool(
    name: 'format',
    description: 'Format a string',
    handler: fn(string $text) => strtoupper($text),
);
```

The callable is invoked with the same arguments the LLM would pass via the tool call. The return value is wrapped in a `Result::from()` automatically.

### Custom Schema and Metadata

When you need the fake to advertise a specific JSON Schema (for example, to test schema validation), pass the `schema` parameter:

```php
$tool = new FakeTool(
    name: 'calculate',
    description: 'Perform arithmetic',
    handler: fn(float $a, float $b) => $a + $b,
    schema: [
        'type' => 'function',
        'function' => [
            'name' => 'calculate',
            'description' => 'Perform arithmetic',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'a' => ['type' => 'number'],
                    'b' => ['type' => 'number'],
                ],
                'required' => ['a', 'b'],
            ],
        ],
    ],
);
```

## Full Test Example

The following Pest test demonstrates the complete pattern: create a `FakeTool`, script a `FakeAgentDriver` with a tool-call step followed by a final-response step, wire them into an `AgentLoop`, and assert on the result:

```php
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Enums\AgentStepType;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Tool\Tools\FakeTool;

it('executes tools and produces final response', function () {
    // 1. Create a fake tool that always returns the same string
    $tool = FakeTool::returning('search', 'Search the web', 'PHP is great');

    // 2. Script the scenario: one tool call, then a final answer
    $driver = FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('search', ['query' => 'php']),
        ScenarioStep::final('PHP is a programming language.'),
    );

    // 3. Build the loop with the fake tool and fake driver
    $loop = AgentLoop::default()
        ->withTools(new Tools($tool))
        ->withDriver($driver);

    // 4. Run the agent
    $state = AgentState::empty()->withUserMessage('Tell me about PHP');
    $result = $loop->execute($state);

    // 5. Assert on the outcome
    expect($result->stepCount())->toBe(2);
    expect($result->status())->toBe(ExecutionStatus::Completed);
    expect($result->finalResponse()->toString())->toContain('PHP');
    expect($result->hasErrors())->toBeFalse();
});
```

## Using `iterate()` for Step-Level Testing

The `AgentLoop::iterate()` method returns a generator that yields the `AgentState` after each completed step. This gives you fine-grained visibility into intermediate states -- useful for asserting on tool execution ordering, intermediate messages, or guard behavior:

```php
use Cognesy\Agents\Enums\AgentStepType;

$steps = [];
foreach ($loop->iterate($state) as $stepState) {
    $steps[] = $stepState;
}

// The first yielded state is after the tool-call step
expect($steps[0]->lastStepType())->toBe(AgentStepType::ToolExecution);

// The second yielded state is after the final-response step
expect($steps[1]->lastStepType())->toBe(AgentStepType::FinalResponse);
expect($steps[1]->status())->toBe(ExecutionStatus::Completed);
```

> **Tip:** The final state yielded by `iterate()` includes the `withExecutionCompleted()` transition, so you can also assert on `ExecutionStatus` and total usage.

## Testing Error Handling

You can verify that your agent handles errors gracefully by scripting error steps:

```php
it('handles tool errors without crashing', function () {
    $tool = new FakeTool(
        name: 'flaky_api',
        description: 'An unreliable API',
        handler: fn() => throw new \RuntimeException('API timeout'),
    );

    $driver = FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('flaky_api', []),
        ScenarioStep::final('I could not reach the API.'),
    );

    $loop = AgentLoop::default()
        ->withTools(new Tools($tool))
        ->withDriver($driver);

    $state = AgentState::empty()->withUserMessage('Call the API');
    $result = $loop->execute($state);

    // The tool error is recorded but the agent continues to the final response
    expect($result->hasFinalResponse())->toBeTrue();
});
```

## Testing Subagent Scenarios

`FakeAgentDriver` supports child steps for subagent testing. When the driver is cloned for a subagent (via `withLLMProvider()` or `withLLMConfig()`), it uses the child steps instead of the parent steps:

```php
$driver = FakeAgentDriver::fromSteps(
    ScenarioStep::toolCall('delegate', ['task' => 'research']),
    ScenarioStep::final('Research complete.'),
)->withChildSteps([
    ScenarioStep::final('Subagent result: found 42 papers.'),
]);
```

If no child steps are provided, subagent drivers default to a single `ScenarioStep::final('ok')`.

## Testing with Events

You can attach event listeners to the `AgentLoop` to capture events emitted during execution. This is useful for verifying that specific lifecycle events fire at the right time:

```php
use Cognesy\Agents\Events\AgentStepCompleted;

$stepsCompleted = [];

$loop = AgentLoop::default()
    ->withTools(new Tools($tool))
    ->withDriver($driver);

$loop->onEvent(AgentStepCompleted::class, function (AgentStepCompleted $event) use (&$stepsCompleted) {
    $stepsCompleted[] = $event;
});

$result = $loop->execute($state);

expect($stepsCompleted)->toHaveCount(2);
```

## Summary

| Component | Purpose |
|---|---|
| `FakeAgentDriver` | Replaces the LLM driver with a scripted sequence of steps |
| `ScenarioStep` | Describes a single loop iteration (final, tool, error, or toolCall) |
| `FakeTool` | Stubs a tool with a fixed return value or custom callable |
| `AgentLoop::iterate()` | Yields state after each step for fine-grained assertions |
| `withChildSteps()` | Scripts subagent behavior when using `FakeAgentDriver` |
