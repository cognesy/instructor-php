---
title: Streaming Responses
description: 'Learn how to handle streaming responses using the Instructor HTTP client API.'
---

Streaming responses are a powerful feature that allows processing data as it arrives from the server, rather than waiting for the entire response to be received. This is particularly valuable when:

- Working with large responses that might exceed memory limits
- Processing real-time data streams
- Handling responses from AI models that generate content token by token
- Building user interfaces that show progressive updates

The Instructor HTTP client API provides robust support for streaming responses across all supported HTTP client implementations.

## Enabling Streaming

To receive a streaming response, you need to configure the request with the `stream` option set to `true`:

```php
use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpRequest;

// Create a streaming request
$request = new HttpRequest(
    url: 'https://api.example.com/stream',
    method: 'GET',
    headers: [
        'Accept' => 'text/event-stream',
    ],
    body: [],
    options: [
        'stream' => true,  // Enable streaming
    ]
);

// Or use the withStreaming method on an existing request
$streamingRequest = $request->withStreaming(true);

// Create a client and send the request
$client = new HttpClient();
$response = $client->withRequest($streamingRequest)->get();
```

The `stream` option tells the HTTP client to treat the response as a stream, which means:

1. It won't buffer the entire response in memory
2. It will provide a way to read the response incrementally
3. The connection will remain open until all data is received or the stream is closed

## Processing Streamed Data

Once you have a streaming response, you can process it using the `stream()` method, which returns a PHP Generator:

```php
$response = $client->handle($streamingRequest);

// Process the stream chunk by chunk
foreach ($response->stream() as $chunk) {
    // Process each chunk of data as it arrives
    echo "Received chunk: $chunk\n";

    // You could parse JSON chunks, update progress, etc.
    // If this is a streaming JSON response, you might need to buffer until
    // you have complete JSON objects
}
```

By default, the `stream()` method reads the response in small chunks. You can control the chunk size by passing a parameter:

```php
// Read in chunks of 1024 bytes
foreach ($response->stream(1024) as $chunk) {
    // Process larger chunks of data
    echo "Received chunk of approximately 1KB: $chunk\n";
}
```

### Example: Downloading a Large File

Here's an example of downloading a large file with streaming to avoid memory issues:

```php
use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\HttpRequestException;

// Create a streaming request
$request = new HttpRequest(
    url: 'https://example.com/large-file.zip',
    method: 'GET',
    headers: [],
    body: [],
    options: ['stream' => true]
);

$client = new HttpClient();

try {
    $response = $client->withRequest($request)->get();

    // Open a file handle to save the file
    $fileHandle = fopen('downloaded-file.zip', 'wb');

    if (!$fileHandle) {
        throw new \RuntimeException("Could not open file for writing");
    }

    // Keep track of bytes received
    $totalBytes = 0;

    // Process the stream and write to file
    foreach ($response->stream(8192) as $chunk) {
        fwrite($fileHandle, $chunk);
        $totalBytes += strlen($chunk);

        // Display progress (if not in a web request)
        echo "\rDownloaded: " . number_format($totalBytes / 1024 / 1024, 2) . " MB";
    }

    // Close the file handle
    fclose($fileHandle);
    echo "\nDownload complete!\n";

} catch (HttpRequestException $e) {
    echo "Download failed: {$e->getMessage()}\n";

    // Clean up if file was partially downloaded
    if (isset($fileHandle) && is_resource($fileHandle)) {
        fclose($fileHandle);
    }
    if (file_exists('downloaded-file.zip')) {
        unlink('downloaded-file.zip');
    }
}
```

This approach allows downloading very large files without loading the entire file into memory.

### Example: Processing Server-Sent Events (SSE)

Server-Sent Events (SSE) are a common streaming format used by many APIs. Here's how to process them:

```php
$request = new HttpClientRequest(
    url: 'https://api.example.com/events',
    method: 'GET',
    headers: [
        'Accept' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
    ],
    body: [],
    options: ['stream' => true]
);

$response = $client->handle($request);

$buffer = '';

foreach ($response->stream() as $chunk) {
    // Add the chunk to our buffer
    $buffer .= $chunk;

    // Process complete events (SSE events are separated by double newlines)
    while (($pos = strpos($buffer, "\n\n")) !== false) {
        // Extract and process the event
        $event = substr($buffer, 0, $pos);
        $buffer = substr($buffer, $pos + 2);

        // Parse the event (SSE format: "field: value")
        $parsedEvent = [];
        foreach (explode("\n", $event) as $line) {
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
                $field = $matches[1];
                $value = $matches[2];
                $parsedEvent[$field] = $value;
            }
        }

        // Process the parsed event
        if (isset($parsedEvent['event'], $parsedEvent['data'])) {
            $eventType = $parsedEvent['event'];
            $eventData = $parsedEvent['data'];

            echo "Received event type: $eventType\n";
            echo "Event data: $eventData\n";

            // You could also parse the data as JSON if appropriate
            if ($eventType === 'update') {
                $data = json_decode($eventData, true);
                if ($data) {
                    echo "Processed update: {$data['message']}\n";
                }
            }
        }
    }
}
```

While this works, processing streaming responses line by line is common enough that the library provides a dedicated middleware for it, as we'll see in the next section.

## Line-by-Line Processing

For many streaming APIs, especially those that send event streams or line-delimited JSON, it's useful to process the response line by line. The library provides the `StreamByLineMiddleware` to simplify this task:

```php
use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\StreamByLine\StreamByLineMiddleware;

// Create a client with the StreamByLineMiddleware
$client = new HttpClient();
$client->withMiddleware(new StreamByLineMiddleware());

// Create a streaming request
$request = new HttpRequest(
    url: 'https://api.example.com/events',
    method: 'GET',
    headers: [],
    body: [],
    options: ['stream' => true]
);

$response = $client->withRequest($request)->get();

// Process the stream line by line
foreach ($response->stream() as $line) {
    // Each $line is a complete line from the response
    echo "Received line: $line\n";

    // Parse the line (e.g., as JSON)
    $event = json_decode($line, true);
    if ($event) {
        // Process the event
        echo "Event type: {$event['type']}\n";
    }
}
```

### Customizing Line Processing

You can customize how lines are parsed by providing a parser function to the middleware:

```php
$lineParser = function (string $line) {
    // Pre-process each line before yielding it
    $trimmedLine = trim($line);
    if (empty($trimmedLine)) {
        return null; // Skip empty lines
    }
    return $trimmedLine;
};

$client->withMiddleware(new StreamByLineMiddleware($lineParser));
```

If your parser returns `null`, that line will be skipped in the stream.

### Example: Processing OpenAI Chat Completions

Here's a practical example of using the `StreamByLineMiddleware` to process streaming responses from the OpenAI API:

```php
use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\StreamByLine\StreamByLineMiddleware;

// OpenAI API requires a parser that handles their SSE format
$openAiParser = function (string $line) {
    // Skip empty lines
    if (trim($line) === '') {
        return null;
    }

    // Remove "data: " prefix from each line
    if (strpos($line, 'data: ') === 0) {
        $line = substr($line, 6);

        // Skip the "[DONE]" message
        if ($line === '[DONE]') {
            return null;
        }

        // Return the parsed line
        return $line;
    }

    return null; // Skip non-data lines
};

// Create a client with the StreamByLineMiddleware
$client = new HttpClient('guzzle'); // Use Guzzle for better streaming support
$client->withMiddleware(new StreamByLineMiddleware($openAiParser));

// Create a request to OpenAI API
$request = new HttpRequest(
    url: 'https://api.openai.com/v1/chat/completions',
    method: 'POST',
    headers: [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $apiKey,
    ],
    body: [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'user', 'content' => 'Write a short poem about coding.'],
        ],
        'stream' => true,
    ],
    options: ['stream' => true]
);

try {
    $response = $client->withRequest($request)->get();

    $fullResponse = '';

    // Process the streaming response
    foreach ($response->stream() as $chunk) {
        // Parse the chunk as JSON
        $data = json_decode($chunk, true);

        if ($data && isset($data['choices'][0]['delta']['content'])) {
            $content = $data['choices'][0]['delta']['content'];
            $fullResponse .= $content;

            // Print each piece as it arrives
            echo $content;
            flush(); // Ensure output is sent immediately
        }
    }

    echo "\n\nFull response:\n$fullResponse\n";

} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
```

This approach allows you to display the AI-generated content to the user in real-time as it's being generated, providing a more responsive user experience.

### Considerations for Streaming

When working with streaming responses, keep these considerations in mind:

1. **Memory Usage**: While streaming reduces memory usage overall, be careful not to accumulate the entire response in memory by appending to a variable unless necessary.

2. **Connection Stability**: Streaming connections can be more sensitive to network issues. Consider implementing error handling and retry logic for more robust applications.

3. **Server Timeouts**: Some servers or proxies might timeout long-running connections. Make sure your infrastructure is configured to allow the necessary connection times.

4. **Middleware Order**: When using middleware that processes streaming responses, the order of middleware can be important. Middleware is executed in the order it's added to the stack.

In the next chapter, we'll explore how to make multiple concurrent requests using request pools, which can significantly improve performance when fetching data from multiple endpoints.