---
title: Response Handling
description: 'Learn how to handle responses from Polyglot.'
---

Polyglot's `InferenceResponse` class provides methods to access the response in different formats.


## Basic Response Handling

```php
<?php
use Cognesy\Polyglot\LLM\Inference;

$inference = new Inference();
$response = $inference->create(
    messages: 'What is the capital of France?'
);

// Get the response as plain text
$text = $response->toText();
echo "Text response: $text\n";

// Get the response as a JSON object (for JSON responses)
$json = $response->toJson();
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
use Cognesy\Polyglot\LLM\Inference;

$inference = new Inference();
$response = $inference->create(
    messages: 'Write a short story about a robot.',
    options: ['stream' => true]
);

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

echo "\n\nComplete response: " . $response->toText();
```




## Handling Tool Calls

For models that support function calling or tools:

```php
<?php
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Polyglot\LLM\Enums\Mode;

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

$inference = new Inference()->withConnection('openai');
$response = $inference->create(
    messages: 'What is the weather in Paris?',
    tools: $tools,
    toolChoice: 'auto',  // Let the model decide when to use tools
    mode: Mode::Tools    // Enable tools mode
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
                'content' => json_encode($result),
                '_metadata' => [
                    'tool_call_id' => $call->id(),
                    'tool_name' => $call->name(),
                ],
            ],
        ];

        $finalResponse = $inference->create(
            messages: $newMessages
        )->toText();

        echo "Final response: $finalResponse\n";
    }
} else {
    echo "Response: " . $response->content() . "\n";
}
```
