---
title: Response Handling
description: Work with `PendingInference`, `InferenceResponse`, and streaming to consume LLM output in the format your application needs.
---

Polyglot's `PendingInference` class represents a pending inference execution. It is
returned by the `Inference` class when you call the `create()` method. The request is
**not sent** to the underlying LLM until you actually access the response data, making
the object a lazy handle over a single inference operation.

## Retrieving Text Content

The simplest way to get the model's response is the `get()` method, which returns
the response content as a plain string:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Messages\Messages;

$pending = Inference::using('openai')
    ->withMessages(Messages::fromString('What is the capital of France?'))
    ->create();

// Get the response as plain text
$text = $pending->get();
echo $text; // "The capital of France is Paris."
```

## Retrieving JSON Data

When you request a JSON response format, use `asJsonData()` to decode the content
directly into an associative array, or `asJson()` to get the raw JSON string:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;

$pending = Inference::using('openai')
    ->withMessages(Messages::fromString('Return JSON with a single "status" field.'))
    ->withResponseFormat(ResponseFormat::jsonObject())
    ->create();

// Decode response content as a PHP array
$data = $pending->asJsonData();
echo $data['status'];

// Or get the raw JSON string
$json = $pending->asJson();
```

## Working with `InferenceResponse`

For full access to every detail of the model's reply, call `response()` to get the
normalized `InferenceResponse` object:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Messages\Messages;

$pending = Inference::using('openai')
    ->withMessages(Messages::fromString('What is the capital of France?'))
    ->create();

$response = $pending->response();

// Content and reasoning
echo "Content: " . $response->content() . "\n";
echo "Reasoning: " . $response->reasoningContent() . "\n";

// Finish reason (returns InferenceFinishReason enum)
echo "Finish reason: " . $response->finishReason()->value . "\n";

// Token usage
$usage = $response->usage();
echo "Input tokens: " . $usage->input() . "\n";
echo "Output tokens: " . $usage->output() . "\n";
echo "Total tokens: " . $usage->total() . "\n";
echo "Cache tokens: " . $usage->cache() . "\n";

// Raw HTTP response data
$httpResponse = $response->responseData();
```

### Available `InferenceResponse` Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `content()` | `string` | The model's text output |
| `reasoningContent()` | `string` | Chain-of-thought / thinking content (if supported) |
| `toolCalls()` | `ToolCalls` | Collection of tool calls made by the model |
| `usage()` | `Usage` | Token counts for the request |
| `finishReason()` | `InferenceFinishReason` | Why the model stopped generating |
| `responseData()` | `HttpResponse` | The underlying raw HTTP response |
| `hasContent()` | `bool` | Whether the response contains text content |
| `hasToolCalls()` | `bool` | Whether the model made any tool calls |
| `hasReasoningContent()` | `bool` | Whether reasoning / thinking content is present |
| `isPartial()` | `bool` | Whether this is a partial (streaming) response |

### Finish Reasons

The `finishReason()` method returns an `InferenceFinishReason` enum. Polyglot
normalizes the many vendor-specific strings into a consistent set of values:

| Value | Meaning |
|-------|---------|
| `Stop` | The model finished naturally |
| `Length` | Output was truncated due to token limits |
| `ToolCalls` | The model wants to invoke a tool |
| `ContentFilter` | Content was blocked by safety filters |
| `Error` | An error occurred during generation |
| `Other` | An unrecognized finish reason |

### Token Usage

The `Usage` object provides detailed token breakdowns including cache and reasoning
tokens:

```php
<?php

$usage = $response->usage();

$usage->inputTokens;       // Input / prompt tokens
$usage->outputTokens;      // Output / completion tokens
$usage->cacheWriteTokens;  // Tokens written to cache
$usage->cacheReadTokens;   // Tokens read from cache
$usage->reasoningTokens;   // Reasoning / thinking tokens

// Convenience accessors
$usage->input();   // Same as inputTokens
$usage->output();  // outputTokens + reasoningTokens
$usage->cache();   // cacheWriteTokens + cacheReadTokens
$usage->total();   // Sum of all token counts
```

## Handling Tool Calls

When the model decides to invoke a tool, you can extract the tool call data using
`asToolCallJsonData()` on `PendingInference`, or inspect the `ToolCalls` collection
on the response object:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Polyglot\Inference\Data\ToolChoice;

$tools = ToolDefinitions::fromArray([
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
                    ],
                ],
                'required' => ['location'],
            ],
        ],
    ],
]);

$response = Inference::using('openai')
    ->with(
        messages: Messages::fromString('What is the weather in Paris?'),
        tools: $tools,
        toolChoice: ToolChoice::auto(),
    )
    ->response();

if ($response->hasToolCalls()) {
    $toolCalls = $response->toolCalls();

    foreach ($toolCalls->all() as $call) {
        echo "Tool: " . $call->name() . "\n";
        echo "Args: " . $call->argsAsJson() . "\n";

        // Access individual argument values
        $location = $call->value('location');
        $unit = $call->value('unit', 'celsius');
    }
}
```

### Quick JSON Extraction from Tool Calls

If you just need the arguments as a PHP array without inspecting the full response,
use the shorthand on `PendingInference`:

```php
<?php

// Single tool call: returns the arguments array directly
$args = $pending->asToolCallJsonData();

// Or as a JSON string
$json = $pending->asToolCallJson();
```

> **Note:** When a single tool call is present, `asToolCallJsonData()` returns that
> call's arguments as an array. When multiple tool calls are present, it returns
> an array of all tool call data.

## Streaming Responses

For long-running completions, streaming lets you display output as it arrives.
Call `stream()` to get an `InferenceStream` and consume deltas:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Messages\Messages;

$stream = Inference::using('openai')
    ->withMessages(Messages::fromString('Write a short story about a robot.'))
    ->stream();

foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
}

// After iteration, get the finalized response
$finalResponse = $stream->final();
echo "\n\nTokens used: " . $finalResponse->usage()->total();
```

### The `PartialInferenceDelta` Object

Each delta yielded during streaming is a `PartialInferenceDelta` with the following
public properties:

| Property | Type | Description |
|----------|------|-------------|
| `contentDelta` | `string` | New text content in this chunk |
| `reasoningContentDelta` | `string` | New reasoning content in this chunk |
| `toolId` | `ToolCallId\|string\|null` | Tool call ID |
| `toolName` | `string` | Tool name (when streaming tool calls) |
| `toolArgs` | `string` | Partial tool arguments JSON |
| `finishReason` | `string` | Set on the final delta |
| `usage` | `?Usage` | Token usage (typically on the final delta) |
| `usageIsCumulative` | `bool` | Whether usage counts are cumulative |

### Stream Methods

The `InferenceStream` class provides several ways to consume and transform the
delta stream:

```php
<?php

// Iterate over visible deltas
foreach ($stream->deltas() as $delta) { /* ... */ }

// Transform each delta
foreach ($stream->map(fn($d) => strtoupper($d->contentDelta)) as $text) {
    echo $text;
}

// Reduce to a single value
$fullText = $stream->reduce(
    fn(string $carry, $delta) => $carry . $delta->contentDelta,
    ''
);

// Filter deltas
foreach ($stream->filter(fn($d) => $d->contentDelta !== '') as $delta) {
    echo $delta->contentDelta;
}

// Collect all deltas into an array
$allDeltas = $stream->all();

// Get the finalized response (drains the stream if needed)
$response = $stream->final();
```

### Using the `onDelta` Callback

Instead of iterating manually, you can register a callback that fires for each
visible delta:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Messages\Messages;

$stream = Inference::using('openai')
    ->withMessages(Messages::fromString('Explain queues in simple terms.'))
    ->stream();

$stream->onDelta(function ($delta) {
    echo $delta->contentDelta;

    // Flush output for real-time display
    if (ob_get_level() > 0) {
        ob_flush();
        flush();
    }
});

// Drain the stream to trigger all callbacks
$response = $stream->final();
```

### Stream Lifecycle

The stream is **one-shot**: once `deltas()` has been fully iterated, calling it
again throws a `LogicException`. If you need to replay the response, work with
the finalized `InferenceResponse` returned by `$stream->final()`.

Calling `final()` before the stream is exhausted will automatically drain all
remaining deltas, ensuring the finalized response is complete.

## Checking for Streaming Mode

If you need to branch your code based on whether a request was configured for
streaming, use the `isStreamed()` method on `PendingInference`:

```php
<?php

$pending = Inference::using('openai')
    ->withMessages(Messages::fromString('Hello!'))
    ->withStreaming()
    ->create();

if ($pending->isStreamed()) {
    foreach ($pending->stream()->deltas() as $delta) {
        echo $delta->contentDelta;
    }
} else {
    echo $pending->get();
}
```
