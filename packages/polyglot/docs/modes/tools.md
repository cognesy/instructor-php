---
title: Tools Mode
description: 'Learn how to use tools mode in Polyglot for LLM function calling.'
---


Tools mode enables function calling, allowing the model to request specific actions to be performed by your application. This is powerful for creating LLM-powered applications that can interact with external systems or perform specific tasks.

## Setting Up Tools

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

$inference = new Inference()->using('openai');  // Tools are best supported by OpenAI

// Define a tool for weather information
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
                    'description' => 'The city and state or country (e.g., "San Francisco, CA")',
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

// Define a tool for flight information
$flightTool = [
    'type' => 'function',
    'function' => [
        'name' => 'get_flight_info',
        'description' => 'Get information about a flight',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'flight_number' => [
                    'type' => 'string',
                    'description' => 'The flight number (e.g., "AA123")',
                ],
                'date' => [
                    'type' => 'string',
                    'description' => 'The date of the flight in YYYY-MM-DD format',
                ],
            ],
            'required' => ['flight_number'],
        ],
    ],
];

// Create an array of tools
$tools = [$weatherTool, $flightTool];

// Make a request that might require tools
$response = $inference->with(
    messages: 'What\'s the weather like in Paris today?',
    tools: $tools,
    toolChoice: 'auto',  // Let the model decide which tool to use
    mode: OutputMode::Tools
)->response();

// Check if the model called a tool
if ($response->hasToolCalls()) {
    $toolCalls = $response->toolCalls();

    foreach ($toolCalls->all() as $call) {
        $toolName = $call->name();
        $args = $call->args();

        echo "Tool called: $toolName\n";
        echo "Arguments: " . json_encode($args, JSON_PRETTY_PRINT) . "\n";

        // Handle the tool call
        if ($toolName === 'get_weather') {
            // In a real application, you would call a weather API here
            $weatherData = simulateWeatherApi($args['location'], $args['unit'] ?? 'celsius');

            // Send the tool result back to the model
            $withToolResult = $inference->with(
                messages: [
                    ['role' => 'user', 'content' => 'What\'s the weather like in Paris today?'],
                    [
                        'role' => 'assistant',
                        'content' => '',
                        '_metadata' => [
                            'tool_calls' => [
                                [
                                    'id' => $call->id(),
                                    'function' => [
                                        'name' => $call->name(),
                                        'arguments' => $call->argsAsJson(),
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'role' => 'tool',
                        'content' => json_encode($weatherData),
                        '_metadata' => [
                            'tool_call_id' => $call->id(),
                            'tool_name' => $call->name(),
                        ],
                    ],
                ]
            )->get();

            echo "Final response: $withToolResult\n";
        }
    }
} else {
    // Model responded directly
    echo "Response: " . $response->content() . "\n";
}

// Simulate a weather API call
function simulateWeatherApi(string $location, string $unit): array {
    return [
        'location' => $location,
        'temperature' => 22,
        'unit' => $unit,
        'conditions' => 'Partly cloudy',
        'humidity' => 65,
    ];
}
```

## Controlling Tool Usage

You can control how tools are used with the `toolChoice` parameter:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

$inference = new Inference()->using('openai');

// Let the model decide whether to use tools
$autoResponse = $inference->with(
    messages: 'What\'s the weather like in Paris?',
    tools: $tools,
    toolChoice: 'auto',
    mode: OutputMode::Tools
)->response();

// Always require the model to use a specific tool
$requiredToolResponse = $inference->with(
    messages: 'What\'s the weather like in Paris?',
    tools: $tools,
    toolChoice: [
        'type' => 'function',
        'function' => [
            'name' => 'get_weather'
        ]
    ],
    mode: OutputMode::Tools
)->response();

// Prevent tool usage
$noToolResponse = $inference->with(
    messages: 'What\'s the weather like in Paris?',
    tools: $tools,
    toolChoice: 'none',
    mode: OutputMode::Tools
)->response();
```


## Streaming Tool Calls

You can also stream tool calls to provide real-time feedback:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

$inference = new Inference()->using('openai');

$response = $inference->with(
    messages: 'What\'s the weather like in Paris today?',
    tools: $tools,
    toolChoice: 'auto',
    mode: OutputMode::Tools,
    options: ['stream' => true]
);

$stream = $response->stream()->responses();

$toolName = '';
$toolId = '';
$toolArgs = '';

foreach ($stream as $partialResponse) {
    // Tool name is being generated
    if (!empty($partialResponse->toolName)) {
        if (empty($toolName)) {
            $toolName = $partialResponse->toolName;
            echo "Tool being called: $toolName\n";
        }
    }

    // Tool ID is received
    if (!empty($partialResponse->toolId) && empty($toolId)) {
        $toolId = $partialResponse->toolId;
    }

    // Tool arguments are being generated
    if (!empty($partialResponse->toolArgs)) {
        $toolArgs .= $partialResponse->toolArgs;
        echo "Receiving tool arguments...\n";
    }

    // Regular content is being generated
    if (!empty($partialResponse->contentDelta)) {
        echo $partialResponse->contentDelta;
        flush();
    }

    // Check for finish reason
    if (!empty($partialResponse->finishReason)) {
        echo "\nFinished with reason: {$partialResponse->finishReason}\n";
    }
}

// Process the complete tool call
if (!empty($toolName) && !empty($toolArgs)) {
    try {
        $args = json_decode($toolArgs, true, 512, JSON_THROW_ON_ERROR);
        echo "\nFinal tool arguments: " . json_encode($args, JSON_PRETTY_PRINT) . "\n";

        // Process the tool call as in the previous example
    } catch (\JsonException $e) {
        echo "Error parsing tool arguments: " . $e->getMessage() . "\n";
    }
}
```



## Provider Support for Tools

Tool support varies across providers:

- **OpenAI**: Comprehensive support with `function_call`/`tool_call` features
- **Anthropic**: Growing support with `tool_use` feature in Claude 3 models
- **Other providers**: Implementation varies; check provider documentation



## When to Use Tools Mode

Tools mode is ideal for:
- Creating agents that can interact with external systems
- Building assistants that need to retrieve real-time information
- Implementing complex workflows that require multiple steps
- Giving the model access to specific capabilities (calculations, API calls, etc.)
