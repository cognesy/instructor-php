---
title: Tool Calling
description: Request native tool calls with explicit tool definitions and process the results.
---

Tool calling enables the model to request specific actions from your application. Instead of generating a text response, the model returns structured function calls with arguments, which your code can execute and optionally feed back into the conversation. This is the foundation for building agents, assistants, and any application that needs to interact with external systems.

## Defining Tools

Tools are defined as arrays following the OpenAI function calling format. Each tool describes a function's name, purpose, and expected parameters:

```php
<?php

$weatherTool = [
    'type' => 'function',
    'function' => [
        'name' => 'get_weather',
        'description' => 'Get the current weather for a location',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string',
                    'description' => 'The city and country (e.g., "Paris, France")',
                ],
                'unit' => [
                    'type' => 'string',
                    'enum' => ['celsius', 'fahrenheit'],
                    'description' => 'The temperature unit to use',
                ],
            ],
            'required' => ['location'],
        ],
    ],
];
```

## Making a Tool Call Request

Pass your tool definitions via the `tools` parameter and control how the model selects tools with `toolChoice`:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$response = Inference::using('openai')
    ->with(
        messages: 'What is the weather like in Paris?',
        tools: [$weatherTool],
        toolChoice: 'auto',
    )
    ->response();
```

## Processing Tool Call Results

The response object provides methods for inspecting whether the model made tool calls and extracting their details:

```php
<?php

if ($response->hasToolCalls()) {
    $toolCalls = $response->toolCalls();

    foreach ($toolCalls->all() as $call) {
        $name = $call->name();      // e.g. 'get_weather'
        $args = $call->args();      // e.g. ['location' => 'Paris, France']
        $id   = $call->id();        // unique call ID for multi-turn conversations

        // Execute the function and use the result...
    }
} else {
    // The model responded with text instead
    echo $response->content();
}
```

## Convenience Accessors

For simple cases where you just need the tool call arguments as data, Polyglot provides shortcut methods:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

// Get tool call arguments as a JSON string
$json = Inference::using('openai')
    ->with(
        messages: 'Get the weather for Paris.',
        tools: [$weatherTool],
        toolChoice: 'auto',
    )
    ->asToolCallJson();

// Or as a decoded PHP array
$data = Inference::using('openai')
    ->with(
        messages: 'Get the weather for Paris.',
        tools: [$weatherTool],
        toolChoice: 'auto',
    )
    ->asToolCallJsonData();
```

When the model returns a single tool call, `asToolCallJsonData()` returns that call's arguments as an array. When multiple tool calls are returned, it returns an array of all calls.

## Controlling Tool Selection

The `toolChoice` parameter controls how the model decides whether to use tools:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

// Let the model decide whether to call a tool or respond with text
$response = Inference::using('openai')
    ->with(
        messages: 'What is the weather like in Paris?',
        tools: $tools,
        toolChoice: 'auto',
    )
    ->response();

// Force the model to call a specific tool
$response = Inference::using('openai')
    ->with(
        messages: 'What is the weather like in Paris?',
        tools: $tools,
        toolChoice: [
            'type' => 'function',
            'function' => ['name' => 'get_weather'],
        ],
    )
    ->response();

// Prevent tool usage entirely (model responds with text)
$response = Inference::using('openai')
    ->with(
        messages: 'What is the weather like in Paris?',
        tools: $tools,
        toolChoice: 'none',
    )
    ->response();
```

## Multiple Tools

You can provide multiple tool definitions in a single request. The model will select the most appropriate one based on the user's message:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$tools = [
    [
        'type' => 'function',
        'function' => [
            'name' => 'get_weather',
            'description' => 'Get the current weather for a location',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => ['type' => 'string'],
                ],
                'required' => ['location'],
            ],
        ],
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'get_flight_info',
            'description' => 'Get information about a flight',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'flight_number' => ['type' => 'string'],
                    'date' => ['type' => 'string'],
                ],
                'required' => ['flight_number'],
            ],
        ],
    ],
];

$response = Inference::using('openai')
    ->with(
        messages: 'What is the status of flight AA123?',
        tools: $tools,
        toolChoice: 'auto',
    )
    ->response();
```

## Using the Fluent API

The fluent builder methods `withTools()` and `withToolChoice()` offer an alternative to passing everything through `with()`:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$response = Inference::using('openai')
    ->withMessages('Get the weather for Paris.')
    ->withTools([$weatherTool])
    ->withToolChoice('auto')
    ->response();
```

## Provider Support

Tool calling support varies across providers:

| Provider | Tool Calling | Tool Choice |
|---|---|---|
| OpenAI | Yes | Yes (auto, none, specific function) |
| Anthropic | Yes | Yes |
| Groq | Yes | Yes |
| Gemini | Yes | Varies by model |
| Other providers | Varies | Varies |

You can query tool support programmatically through `DriverCapabilities::supportsToolCalling()` and `DriverCapabilities::supportsToolChoice()`.

## When to Use Tool Calling

Tool calling is ideal for:

- Building agents that interact with external APIs and services
- Creating assistants that retrieve real-time information
- Implementing multi-step workflows where the model orchestrates actions
- Extracting structured data using function schemas (an alternative to JSON Schema mode)
- Giving the model access to specific capabilities like calculations, database queries, or file operations
