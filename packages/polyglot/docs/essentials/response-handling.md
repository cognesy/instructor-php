---
title: Response Handling
description: 'Learn how to handle responses from Polyglot.'
---

Polyglot's `PendingInference` class represents pending inference execution.
It provides methods to access the response in different formats, but also
provides access to streaming responses. It does not execute the request to
underlying LLM until you actually access the response data.

It is returned by the `Inference` class when you call the `create()` method.

## Basic Response Handling

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = new Inference();
$response = $inference
    ->withMessages('What is the capital of France?')
    ->create();

// Get the response as plain text
$text = $response->get();
echo "Text response: $text\n";

// Get the response as a JSON object (for JSON responses)
$json = $response->asJsonData();
echo "JSON response: " . json_encode($json) . "\n";

// Get the full response object
$fullResponse = $response->response();

// Access specific information
echo "Content: " . $fullResponse->content() . "\n";
echo "Finish reason: " . $fullResponse->finishReason() . "\n";
echo "Usage - Total tokens: " . $fullResponse->usage()->total() . "\n";
echo "Usage - Input tokens: " . $fullResponse->usage()->input() . "\n";
echo "Usage - Output tokens: " . $fullResponse->usage()->output() . "\n";
```



## Working with Streaming Responses

For streaming responses, use the `stream()` method:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = new Inference();
$response = $inference
    ->withMessages('Write a short story about a robot.')
    ->withStreaming()
    ->create();

// Get a generator that yields partial responses
$stream = $response->stream()->responses();

echo "Story: ";
foreach ($stream as $partialResponse) {
    // Output each chunk as it arrives
    echo $partialResponse->contentDelta;

    // Flush the output buffer to show progress in real-time
    if (ob_get_level() > 0) {
        ob_flush();
        flush();
    }
}

echo "\n\nComplete response: " . $response->get();
```




## Handling Tool Calls

For models that support function calling or tools:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

$tools = [
    [
        'type' => 'function',
        'function' => [
            'name' => 'get_weather',
            'description' => 'Get the current weather in a location',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'The city and state, e.g. San Francisco, CA',
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
    ],
];

$inference = Inference::using('openai');
$response = $inference->with(
    messages: 'What is the weather in Paris?',
    tools: $tools,
    toolChoice: 'auto',  // Let the model decide when to use tools
    mode: OutputMode::Tools    // Enable tools mode
)->response();

// Check if there are tool calls
if ($response->hasToolCalls()) {
    $toolCalls = $response->toolCalls();
    foreach ($toolCalls->all() as $call) {
        echo "Tool called: " . $call->name() . "\n";
        echo "Arguments: " . $call->argsAsJson() . "\n";

        // In a real application, you would call the actual function here
        // and then send the result back to the model
        $result = ['temperature' => 22, 'unit' => 'celsius', 'condition' => 'sunny'];

        // Continue the conversation with the tool result
        $newMessages = [
            ['role' => 'user', 'content' => 'What is the weather in Paris?'],
            [
                'role' => 'assistant',
                'content' => '',
                '_metadata' => [
                    'tool_calls' => [
                        [
                            'id' => $call->id()?->toString() ?? '',
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
                'content' => json_encode($result),
                '_metadata' => [
                    'tool_call_id' => $call->id()?->toString() ?? '',
                    'tool_name' => $call->name(),
                ],
            ],
        ];

        $finalResponse = $inference->with(
            messages: $newMessages
        )->get();

        echo "Final response: $finalResponse\n";
    }
} else {
    echo "Response: " . $response->content() . "\n";
}
```
