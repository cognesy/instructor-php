---
title: 'Testing Agents'
description: 'Test agents deterministically using FakeAgentDriver without LLM calls'
---

# Testing Agents

## FakeAgentDriver

Script deterministic agent behavior without LLM calls:

```php
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;

$driver = FakeAgentDriver::fromResponses('Hello!');
// or
$driver = FakeAgentDriver::fromSteps(
    ScenarioStep::toolCall('search', ['query' => 'php'], 'Results found'),
    ScenarioStep::final('Based on the search, here is the answer.'),
);
```

### ScenarioStep Types

```php
ScenarioStep::final('response text');       // Final response, loop stops
ScenarioStep::tool('intermediate text');     // Tool step, loop continues
ScenarioStep::error('error text');           // Error step
ScenarioStep::toolCall(                      // Tool call with execution
    toolName: 'bash',
    args: ['command' => 'ls'],
    response: '',
    executeTools: true,                      // actually run the tool
);
```

### Full Test Example

```php
use Cognesy\Agents\AgentLoop;use Cognesy\Agents\Collections\Tools;use Cognesy\Agents\Data\AgentState;use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;use Cognesy\Agents\Drivers\Testing\ScenarioStep;use Cognesy\Agents\Tool\Tools\MockTool;

it('executes tools and produces final response', function () {
    $tool = MockTool::returning('search', 'Search the web', 'PHP is great');

    $driver = FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('search', ['query' => 'php']),
        ScenarioStep::final('PHP is a programming language.'),
    );

    $loop = AgentLoop::default()
        ->withTools(new Tools($tool))
        ->withDriver($driver);

    $state = AgentState::empty()->withUserMessage('Tell me about PHP');
    $result = $loop->execute($state);

    expect($result->stepCount())->toBe(2);
    expect($result->finalResponse()->toString())->toContain('PHP');
});
```

## Using iterate() for Step-Level Testing

```php
$steps = [];
foreach ($loop->iterate($state) as $stepState) {
    $steps[] = $stepState;
}

expect($steps)->toHaveCount(2);
expect($steps[0]->lastStepType())->toBe(AgentStepType::ToolExecution);
expect($steps[1]->lastStepType())->toBe(AgentStepType::FinalResponse);
```

## MockTool

Stub tools with fixed return values or custom logic:

```php
use Cognesy\Agents\Tool\Tools\MockTool;

// Fixed return value
$tool = MockTool::returning('search', 'Search the web', 'result text');

// Custom logic
$tool = new MockTool('calculator', 'Math operations', fn(string $expr) => eval("return {$expr};"));
```

Combine with `FakeAgentDriver` to test the full loop without any real LLM or tool calls, or with `ScenarioStep::toolCall(..., executeTools: true)` to actually execute the mock tool during the scenario.

## FakeInferenceDriver

For testing the real `ToolCallingDriver` with scripted LLM responses:

```php
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

$fakeDriver = new FakeInferenceDriver([
    new InferenceResponse(content: 'Hello!'),
]);

$llm = LLMProvider::new()->withDriver($fakeDriver);
$driver = new ToolCallingDriver(
    inference: InferenceRuntime::fromProvider($llm),
    llm: $llm,
);
```

This tests the full driver pipeline (message compilation, response parsing) without network calls.
