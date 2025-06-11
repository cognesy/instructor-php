---
title: Overview of Streaming
description: 'Learn how to work with streaming responses in Polyglot.'
---

Streaming LLM responses may be preferred for user experience and system performance. Polyglot makes it easy to implement streaming with a consistent API across different providers.

Streaming responses are a powerful feature of modern LLM APIs that allow you to receive and process model outputs incrementally as they're being generated, rather than waiting for the complete response. This chapter covers how to work with streaming responses in Polyglot, from basic setup to advanced processing techniques.


## Benefits of Streaming

Streaming responses offer several advantages:

1. **Improved User Experience**: Display content to users as it's generated, creating a more responsive interface
2. **Reduced Latency Perception**: Users see the beginning of a response almost immediately
3. **Progressive Processing**: Begin processing early parts of the response while later parts are still being generated
4. **Handling Long Outputs**: Efficiently process responses that may be very long without hitting timeout limits
5. **Early Termination**: Stop generation early if needed, saving resources


## Enabling Streaming

Enabling streaming in Polyglot is straightforward - you need to set the `stream` option to `true` in your request:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = new Inference();
$response = $inference->with(
    messages: 'Write a short story about a space explorer.',
    options: ['stream' => true]  // Enable streaming
);
```

Once you have a streaming-enabled response, you can access the stream using the `stream()` method:

```php
// Get the stream of partial responses
$stream = $response->stream();
```


## Basic Stream Processing

The most common way to process a stream is to iterate through the partial responses:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = new Inference();
$response = $inference->with(
    messages: 'Write a short story about a space explorer.',
    options: ['stream' => true]
);

// Get a generator that yields partial responses
$stream = $response->stream()->responses();

echo "Story: ";
foreach ($stream as $partialResponse) {
    // Output each chunk as it arrives
    echo $partialResponse->contentDelta;

    // Flush the output buffer to show progress in real-time (for CLI or streaming HTTP responses)
    if (ob_get_level() > 0) {
        ob_flush();
        flush();
    }
}
echo "\n";
```

### Understanding Partial Responses

Each iteration of the stream yields a `PartialInferenceResponse` object with these key properties:

- `contentDelta`: The new content received in this chunk
- `content`: The accumulated content up to this point
- `finishReason`: The reason why the response finished (empty until the final chunk)
- `usage`: Token usage statistics

```php
foreach ($stream as $partialResponse) {
    // The new content in this chunk
    echo "New content: " . $partialResponse->contentDelta . "\n";

    // The total content received so far
    echo "Total content so far: " . $partialResponse->content() . "\n";

    // Check if this is the final chunk
    if ($partialResponse->finishReason !== '') {
        echo "Response finished: " . $partialResponse->finishReason . "\n";
    }
}
```

## Retrieving the Final Response

After processing the stream, you can get the complete response:

```php
// Method 1: Using the original response object's get() method
$completeText = $response->vectors();

// Method 2: Getting the final state from the stream
$finalResponse = $response->stream()->final();
$completeText = $finalResponse->content();
```


