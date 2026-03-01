---
title: Streaming
description: 'Learn how to troubleshoot streaming issues when using Polyglot.'
---

Streaming responses can encounter specific problems.

## Symptoms

- Streams cutting off prematurely
- Errors during stream processing
- Partial or incomplete responses

## Solutions

1. **Connection Timeouts**: Increase timeout settings for streaming responses

```php
<?php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

// Create a custom HTTP client with longer timeouts
$config = new HttpClientConfig(
    requestTimeout: 180,  // 3 minutes for the entire request
    connectTimeout: 10,   // 10 seconds to establish connection
    idleTimeout: 60       // 60 seconds allowed between stream chunks
);

$httpClient = (new HttpClientBuilder())
    ->withConfig($config->withOverrides(['driver' => 'guzzle']))
    ->create();
$inference = Inference::fromRuntime(InferenceRuntime::using(
    preset: 'openai',
    httpClient: $httpClient,
));

// Use streaming with the custom client
$response = $inference->with(
    messages: 'Write a long story about a space explorer.',
    options: ['stream' => true]
);

$stream = $response->stream()->responses();
foreach ($stream as $partial) {
    echo $partial->contentDelta;
    flush();
}
```

2. **Buffer Flushing**: Ensure output buffers are properly flushed during streaming
```php
foreach ($stream as $partial) {
    echo $partial->contentDelta;

    // Flush output buffer to ensure content is sent immediately
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}
```

3. **Error Handling in Streams**: Implement specific error handling for streams
```php
<?php
try {
    $response = $inference->with(
        messages: 'Write a long story.',
        options: ['stream' => true]
    );

    try {
        $stream = $response->stream()->responses();
        $content = '';

        foreach ($stream as $partial) {
            $content .= $partial->contentDelta;
            echo $partial->contentDelta;
            flush();
        }
    } catch (\Exception $streamException) {
        echo "\nStream error: " . $streamException->getMessage() . "\n";

        // If we got a partial response before the error, use it
        if (!empty($content)) {
            echo "Partial content received: " . strlen($content) . " characters\n";
        }
    }
} catch (\Throwable $e) {
    echo "Request failed: " . $e->getMessage() . "\n";
}
```

4. **Fallback to Non-streaming**: Implement a fallback to non-streaming mode
```php
<?php
function getResponse(string $prompt, bool $preferStreaming = true): string {
    $inference = new Inference();

    try {
        if ($preferStreaming) {
            // Try streaming first
            $response = $inference->with(
                messages: $prompt,
                options: ['stream' => true]
            );

            $content = '';
            foreach ($response->stream()->responses() as $partial) {
                $content .= $partial->contentDelta;
                // Output can be done here if needed
            }

            return $content;
        }
    } catch (\Exception $e) {
        echo "Streaming failed, falling back to non-streaming mode\n";
    }

    // Fallback to non-streaming
    return $inference->with(messages: $prompt)->get();
}
```
