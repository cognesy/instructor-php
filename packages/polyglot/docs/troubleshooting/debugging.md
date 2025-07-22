---
title: Debugging Requests and Responses
description: 'Debugging LLM interactions is essential for troubleshooting and optimizing your applications.'
---

Polyglot debug mode provides a simple way to enable debugging for LLM interactions. Debugging is essential for troubleshooting and optimizing your applications. It allows you to inspect the requests sent to the LLM and the responses received, helping you identify issues and improve performance.



## Enabling Debug Mode

Polyglot provides a simple way to enable debug mode:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

// Enable debug mode when creating the inference object
$inference = (new Inference())
    ->withDebugPreset('on');

// Make a request - debug output will show the request and response details
$response = $inference->with(
    messages: 'What is the capital of France?'
)->get();
```




### HTTP Request Inspection with Middleware

You can manually add debugging middleware to inspect raw HTTP requests and responses.

In this example we're using built-in middleware, but you can also create your own custom middleware.

```php
<?php
use Cognesy\Http\Middleware\Debug\DebugMiddleware;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Inference;

// Create a custom debug middleware with specific options
$debugMiddleware = new DebugMiddleware([
    'requestUrl' => true,
    'requestHeaders' => true,
    'requestBody' => true,
    'responseHeaders' => true,
    'responseBody' => true,
]);

// Create an HTTP client with the debug middleware
$httpClient = new HttpClient();
$httpClient->withMiddleware($debugMiddleware);

// Use the HTTP client with Inference
$inference = new Inference();
$inference->withHttpClient($httpClient);

// Make a request
$response = $inference->with(
    messages: 'What is the capital of France?'
)->get();
```




### Event Listeners

Use event listeners to trace the flow of requests and responses:

```php
<?php
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Events\InferenceRequested;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\Inference;

// Create an event dispatcher
$events = new EventDispatcher();

// Add listeners
$events->listen(InferenceRequested::class, function (InferenceRequested $event) {
    echo "Request sent: " . json_encode($event->request->toArray()) . "\n";
});

$events->listen(InferenceResponseCreated::class, function (InferenceResponseCreated $event) {
    echo "Response received: " . substr($event->inferenceResponse->content(), 0, 50) . "...\n";
    echo "Token usage: " . $event->inferenceResponse->usage()->total() . "\n";
});

// Create an inference object with the event dispatcher
$inference = new Inference(events: $events);

// Make a request
$response = $inference->with(
    messages: 'What is the capital of France?'
)->get();
```






## Logging to Files

For more persistent debugging, you can log to files:

```php
<?php
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Events\InferenceRequested;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\Inference;

// Create a function to log to file
function logToFile(string $message, string $filename = 'llm_debug.log'): void {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(
        $filename,
        "[$timestamp] $message" . PHP_EOL,
        FILE_APPEND
    );
}

// Create a custom event dispatcher
$events = new EventDispatcher();

// Listen for request events
$events->listen(InferenceRequested::class, function (InferenceRequested $event) {
    $request = $event->request;
    logToFile("REQUEST: " . json_encode($request->toArray()));
});

// Listen for response events
$events->listen(InferenceResponseCreated::class, function (InferenceResponseCreated $event) {
    $response = $event->inferenceResponse;
    logToFile("RESPONSE: " . json_encode($response->toArray()));
});

// Create an inference object with the custom event dispatcher
$inference = new Inference(events: $events);

// Make a request
$response = $inference->with(
    messages: 'What is artificial intelligence?'
)->get();
```
