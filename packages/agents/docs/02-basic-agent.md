---
title: 'Basic Agent'
description: 'Create your first agent with AgentLoop, add tools, customize drivers, and understand the execution lifecycle'
---

# Basic Agent

This guide walks you through building agents with `AgentLoop` -- the core execution engine of the Agents package. You will learn how to send messages, add tools, customize behavior, observe execution, and test agents without making LLM calls.

## Hello World

The simplest possible agent sends a message to a language model and returns the response:

```php
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;

$loop = AgentLoop::default();
$state = AgentState::empty()->withUserMessage('What is 2+2?');
$result = $loop->execute($state);

echo $result->finalResponse()->toString();
// "2 + 2 equals 4."
```

Three things happen here:

1. `AgentLoop::default()` creates a loop with the default `ToolCallingDriver`, which connects to whatever LLM provider is configured in your environment (typically via `OPENAI_API_KEY` or similar).
2. `AgentState::empty()` creates a fresh, immutable state with no messages, no history, and no execution context. Calling `withUserMessage()` returns a *new* state with the message appended -- the original remains empty.
3. `$loop->execute($state)` runs the step loop. The driver sends the message to the LLM, receives a text response with no tool calls, and the loop detects there is nothing more to do. It returns the final `AgentState` containing the complete execution history.

The returned state carries everything that happened: the LLM's response, token usage, step timing, finish reason, and any errors. You access the model's final text output through `finalResponse()->toString()`.

## Understanding the Execution Lifecycle

Every call to `execute()` follows the same lifecycle:

1. **Prepare execution** -- The loop ensures a fresh `ExecutionState` with a unique execution ID and sets the status to `InProgress`.
2. **Before step** -- Lifecycle hooks fire. Guard hooks (step limits, token limits, time limits) check whether execution should be stopped before the next LLM call.
3. **Driver step** -- The driver compiles messages from the current state, sends them to the LLM, receives a response, and executes any requested tool calls. The result is captured as an `AgentStep`.
4. **After step** -- Lifecycle hooks fire again. Hooks can inspect the step result, transform state, or trigger summarization.
5. **Continuation check** -- The loop evaluates whether to continue. It stops when: (a) no tool calls were returned, (b) a stop signal was emitted by a hook, or (c) the execution was explicitly continued by a hook. If tool calls were present, the loop repeats from step 2.
6. **After execution** -- Final hooks fire and the execution status is set to `Completed`, `Stopped`, or `Failed`.

This means a simple question-and-answer exchange completes in a single step, while tool-using agents may run for many steps as the model iterates between reasoning and acting.

## Adding a Tool

Tools give the agent the ability to act on the world. You define a tool as a callable, and the LLM decides when and how to invoke it based on the function's name, parameter types, and docblock:

```php
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Tool\Tools\FunctionTool;

$weather = FunctionTool::fromCallable(
    function (string $city): string {
        return "Weather in {$city}: 72F, sunny";
    }
);

$loop = AgentLoop::default()->withTool($weather);
$state = AgentState::empty()->withUserMessage('What is the weather in Paris?');
$result = $loop->execute($state);

echo $result->finalResponse()->toString();
// "The weather in Paris is 72°F and sunny."
```

When the LLM receives this request, it recognizes that a weather tool is available and returns a tool call instead of a direct answer. The loop executes the tool, feeds the result back as a tool response message, and calls the LLM again. This time the model has the weather data and produces a natural language answer. The loop sees no further tool calls and stops.

`FunctionTool::fromCallable()` uses reflection to automatically generate the tool's JSON schema from the callable's signature. The function name becomes the tool name, parameter types become schema properties, and any PHPDoc `@param` descriptions become property descriptions. This means well-typed, well-documented functions produce high-quality tool schemas with zero manual configuration.

### Multiple Tools

You can add multiple tools to a single loop. Each call to `withTool()` returns a new `AgentLoop` instance with the additional tool registered:

```php
$loop = AgentLoop::default()
    ->withTool($weatherTool)
    ->withTool($calculatorTool)
    ->withTool($searchTool);
```

The LLM sees all available tools in each request and chooses which to call (or none) based on the user's message.

## System Prompt

A system prompt establishes the agent's persona, instructions, and constraints. It is sent as a cached context prefix on every LLM request, so the model always has it in scope:

```php
$state = AgentState::empty()
    ->withSystemPrompt('You are a concise weather assistant. Always respond with temperature in Celsius.')
    ->withUserMessage('What is the weather in Paris?');
```

Since `AgentState` is immutable, you can create a base state with a system prompt and reuse it across multiple conversations by calling `withUserMessage()` each time:

```php
$baseState = AgentState::empty()
    ->withSystemPrompt('You are a helpful coding assistant.');

$result1 = $loop->execute($baseState->withUserMessage('Explain closures in PHP.'));
$result2 = $loop->execute($baseState->withUserMessage('What is a generator?'));
```

## Stepping Through Execution

Sometimes you need to observe or react to each step as it happens, rather than waiting for the final result. The `iterate()` method returns a generator that yields the state after every step:

```php
foreach ($loop->iterate($state) as $stepState) {
    $step = $stepState->currentStepOrLast();
    echo sprintf(
        "Step %d: %s (tokens: %d)\n",
        $stepState->stepCount(),
        $step->stepType()->value,
        $step->usage()->total(),
    );
}
```

This is useful for progress reporting, streaming intermediate results to a UI, or implementing custom early-exit logic. The final state yielded by the generator is the same state you would get from `execute()`.

## Inspecting Results

The returned `AgentState` provides rich access to everything that happened during execution:

```php
$result = $loop->execute($state);

// The model's final text output
echo $result->finalResponse()->toString();

// Execution status: Completed, Stopped, or Failed
echo $result->status()->value;

// Total token usage across all steps
$usage = $result->usage();
echo "Input: {$usage->inputTokens}, Output: {$usage->outputTokens}";

// Number of steps executed
echo $result->stepCount();

// Total execution duration in seconds
echo $result->executionDuration();

// Whether any errors occurred
if ($result->hasErrors()) {
    echo $result->errors()->toMessagesString();
}

// Why the loop stopped
$stopReason = $result->lastStopReason();
echo $stopReason?->value; // "completed", "steps_limit", "token_limit", etc.

// Debug summary (useful during development)
print_r($result->debug());
```

## Observing Events

The `AgentLoop` emits events at every significant point in the lifecycle. You can listen for specific event types or wiretap all events:

```php
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Agents\Events\ToolCallCompleted;

// Listen for a specific event
$loop->onEvent(AgentStepCompleted::class, function (AgentStepCompleted $event) {
    echo "Step {$event->stepNumber} completed, tokens: {$event->usage->total()}\n";
});

// Wiretap all events (useful for debugging)
$loop->wiretap(function (object $event) {
    echo get_class($event) . "\n";
});

$result = $loop->execute($state);
```

Events are dispatched for execution start/complete/fail, step start/complete, inference requests/responses, tool call start/complete/blocked, stop signals, and token usage reports. This makes it straightforward to build logging, monitoring, or streaming integrations without modifying agent logic.

## Customizing the Driver

### Choosing a Model

By default, `AgentLoop::default()` uses whatever LLM provider and model are configured in your environment. To use a specific provider or model, create the driver explicitly:

```php
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Events\Dispatchers\EventDispatcher;

$events = new EventDispatcher();
$llm = LLMProvider::using('anthropic');

$loop = AgentLoop::default()->withDriver(
    new ToolCallingDriver(
        llm: $llm,
        inference: InferenceRuntime::fromProvider($llm, events: $events),
        events: $events,
    )
);
```

### ReAct Driver

The `ReActDriver` implements the Thought/Action/Observation reasoning pattern. Instead of relying on native function-calling APIs, it prompts the model to produce structured decisions about what to do next. This can be useful with models that have weaker function-calling support, or when you want the model's reasoning to be explicitly visible:

```php
use Cognesy\Agents\Drivers\ReAct\ReActDriver;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

$events = new EventDispatcher();
$llm = LLMProvider::new();
$inference = InferenceRuntime::fromProvider($llm, events: $events);
$structuredOutput = new StructuredOutputRuntime(
    inference: $inference,
    events: $events,
    config: (new StructuredOutputConfigBuilder())->create(),
);

$loop = AgentLoop::default()->withDriver(new ReActDriver(
    inference: $inference,
    structuredOutput: $structuredOutput,
    model: 'gpt-4o',
));
```

## Testing Without an LLM

The `FakeAgentDriver` lets you write deterministic agent tests by scripting the exact sequence of responses the "model" will produce. No API keys, no network calls, no flaky tests:

```php
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;

// Script a two-step scenario: tool use, then final answer
$driver = FakeAgentDriver::fromSteps(
    ScenarioStep::toolCall('weather', ['city' => 'Paris']),
    ScenarioStep::final('The weather in Paris is 72F and sunny.'),
);

$loop = AgentLoop::default()
    ->withDriver($driver)
    ->withTool($weatherTool);

$result = $loop->execute(
    AgentState::empty()->withUserMessage('Weather in Paris?')
);

assert($result->finalResponse()->toString() === 'The weather in Paris is 72F and sunny.');
assert($result->stepCount() === 2);
```

You can also create a driver that always returns the same response, which is useful for simple unit tests:

```php
$driver = FakeAgentDriver::fromResponses('Hello!', 'Goodbye!');
$loop = AgentLoop::default()->withDriver($driver);
```

The first execution returns "Hello!", the second returns "Goodbye!", and any subsequent executions repeat "Goodbye!".

## Using AgentBuilder

When your agent needs multiple capabilities -- tools, guards, a specific LLM, custom hooks -- manual construction becomes verbose. `AgentBuilder` provides a declarative composition layer:

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Bash\UseBash;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseLLMConfig;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Polyglot\Inference\LLMProvider;

$loop = AgentBuilder::base()
    ->withCapability(new UseLLMConfig(
        llm: LLMProvider::using('anthropic'),
    ))
    ->withCapability(new UseTools($weatherTool, $searchTool))
    ->withCapability(new UseBash())
    ->withCapability(new UseGuards(
        maxSteps: 15,
        maxTokens: 16384,
        maxExecutionTime: 120.0,
    ))
    ->build();

$result = $loop->execute($state);
```

Each capability is a small, focused class that knows how to install its tools, hooks, and configuration onto the agent. They compose cleanly because they operate on a shared `CanConfigureAgent` interface without needing to know about each other.

The `UseGuards` capability is particularly important for production use. It installs hooks that enforce step limits, token budgets, and execution time limits, preventing runaway agents from burning through your API quota. The defaults are 20 steps, 32768 tokens, and 300 seconds.

See [AgentBuilder & Capabilities](13-agent-builder.md) for the full list of built-in capabilities and how to create your own.

## Next Steps

- **[AgentBuilder & Capabilities](13-agent-builder.md)** -- Learn how capabilities compose and explore the full catalog (bash, file tools, subagents, summarization, task planning, structured output, and more).
- **[Agent Templates](14-agent-templates.md)** -- Define agents in Markdown, YAML, or JSON when configuration should be data-driven.
- **[Session Runtime](16-session-runtime.md)** -- Persist agent sessions for multi-turn chat interfaces and long-running workflows.
