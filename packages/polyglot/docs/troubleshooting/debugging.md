---
title: Debugging
description: Use runtime events, wiretapping, and HTTP middleware to inspect requests and responses.
---

Debugging LLM interactions is essential for troubleshooting and optimizing your applications. Polyglot provides several layers of observability, from high-level event listeners to raw HTTP request inspection.

## Wiretapping the Runtime

The simplest debugging path is to attach a wiretap listener to the `InferenceRuntime`. The wiretap receives every event dispatched during the request lifecycle, including request construction, driver selection, streaming deltas, and the final response.

```php
<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$runtime = InferenceRuntime::fromConfig(new LLMConfig(
    driver: 'openai',
    apiUrl: 'https://api.openai.com/v1',
    apiKey: (string) getenv('OPENAI_API_KEY'),
    endpoint: '/chat/completions',
    model: 'gpt-4.1-nano',
))->wiretap(function ($event): void {
    echo get_class($event) . PHP_EOL;
});

$text = Inference::fromRuntime($runtime)
    ->withMessages('Say hello.')
    ->get();
```

This prints every event class name as it fires, giving you an immediate view of the request flow without modifying your application code.

## Listening for Specific Events

When you only care about certain events, use `onEvent()` to register targeted listeners instead of a wiretap. This avoids noise from events you do not need.

```php
<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Events\InferenceRequested;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$runtime = InferenceRuntime::fromConfig(
    LLMConfig::fromPreset('openai'),
);

$runtime->onEvent(
    InferenceRequested::class,
    function (InferenceRequested $event): void {
        echo "Request sent to model\n";
    },
);

$runtime->onEvent(
    InferenceResponseCreated::class,
    function (InferenceResponseCreated $event): void {
        $response = $event->inferenceResponse;
        echo "Response: " . substr($response->content(), 0, 80) . "...\n";
        echo "Tokens: " . $response->usage()->total() . "\n";
    },
);

$text = Inference::fromRuntime($runtime)
    ->withMessages('What is the capital of France?')
    ->get();
```

### Available Events

Polyglot dispatches events at each stage of the inference lifecycle:

| Event | When it fires |
|---|---|
| `InferenceStarted` | Before the first attempt begins |
| `InferenceRequested` | When a request is about to be sent |
| `InferenceAttemptStarted` | At the start of each retry attempt |
| `InferenceAttemptSucceeded` | When an attempt receives a successful response |
| `InferenceAttemptFailed` | When an attempt fails (before retry) |
| `StreamEventReceived` | When a raw SSE event arrives during streaming |
| `StreamEventParsed` | After a stream event is parsed into a delta |
| `StreamFirstChunkReceived` | When the first visible delta arrives (useful for TTFC) |
| `PartialInferenceDeltaCreated` | For each visible streaming delta |
| `InferenceResponseCreated` | When the final response is assembled |
| `InferenceCompleted` | After the entire inference flow finishes |
| `InferenceFailed` | When all retry attempts are exhausted |
| `InferenceUsageReported` | When token usage data is available |
| `InferenceDriverBuilt` | When the driver is constructed (includes redacted config) |

## Logging to Files

For persistent debugging, write event data to a log file:

```php
<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Events\InferenceRequested;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

function logToFile(string $message, string $filename = 'llm_debug.log'): void {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(
        $filename,
        "[$timestamp] $message" . PHP_EOL,
        FILE_APPEND,
    );
}

$runtime = InferenceRuntime::fromConfig(LLMConfig::fromPreset('openai'));

$runtime->onEvent(
    InferenceRequested::class,
    function (InferenceRequested $event): void {
        logToFile("REQUEST: " . json_encode($event->request->toArray()));
    },
);

$runtime->onEvent(
    InferenceResponseCreated::class,
    function (InferenceResponseCreated $event): void {
        logToFile("RESPONSE: " . json_encode($event->inferenceResponse->toArray()));
    },
);

$text = Inference::fromRuntime($runtime)
    ->withMessages('What is artificial intelligence?')
    ->get();
```

## HTTP-Level Inspection

If you need to see the raw HTTP request and response bodies, inject a custom HTTP client with middleware. This is useful when you suspect Polyglot is sending an unexpected payload, or when the provider returns an error body that higher-level events do not surface.

```php
<?php

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$httpClient = HttpClient::fromConfig(new HttpClientConfig(
    connectTimeout: 10,
    requestTimeout: 60,
));

$runtime = InferenceRuntime::fromConfig(
    config: LLMConfig::fromPreset('openai'),
    httpClient: $httpClient,
);

$text = Inference::fromRuntime($runtime)
    ->withMessages('Test message')
    ->get();
```

You can add custom middleware to the HTTP client using `withMiddleware()` to log, transform, or inspect requests and responses at the transport layer. This is especially helpful when working behind proxies, or when provider error messages are only visible in the raw HTTP body.

## Tips for Effective Debugging

- **Start with wiretap.** It gives a complete picture with no configuration.
- **Narrow to specific events** once you know which stage of the flow is failing.
- **Check the `InferenceDriverBuilt` event** to confirm the correct driver and configuration were resolved. The config is automatically redacted to hide API keys.
- **Use file logging in production** rather than `echo`, so you can review logs after the fact.
- **For streaming issues**, listen for `StreamFirstChunkReceived` to measure time-to-first-chunk, and `PartialInferenceDeltaCreated` to verify deltas are arriving.
