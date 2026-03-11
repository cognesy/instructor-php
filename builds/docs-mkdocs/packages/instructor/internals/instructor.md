---
title: Instructor
description: 'The main public types in the package.'
---

## Overview

The public surface of the `cognesy/instructor-struct` package is intentionally small.
Most interactions happen through a handful of classes that form a clear request-build-execute
pipeline.

| Class | Role |
|---|---|
| `StructuredOutput` | Immutable request builder and main facade |
| `StructuredOutputRuntime` | Configures the provider, event handling, and runtime behavior |
| `PendingStructuredOutput` | Lazy execution handle returned by `create()` |
| `StructuredOutputStream` | Streaming read interface for partial and sequence updates |
| `StructuredOutputResponse` | Wraps the parsed value together with the raw provider response |

Most users should stay at this level. The sections below explain each type in detail.


## `StructuredOutput`

`StructuredOutput` is the main entry point to the library. It is an immutable builder --
every `with*()` call returns a new copy, so you can safely reuse a configured instance
across multiple requests.

### Creating an Instance

```php
use Cognesy\Instructor\StructuredOutput;

// Defaults -- uses the default LLM provider
$so = new StructuredOutput();

// From a named preset (resolves YAML config)
$so = StructuredOutput::using('openai');

// From an explicit LLMConfig
$so = StructuredOutput::fromConfig($llmConfig);

// With a custom runtime
$so = (new StructuredOutput())->withRuntime($runtime);
// @doctest id="691c"
```

### Setting Request Parameters

The builder exposes fine-grained setters as well as a convenience `with()` method that
accepts all parameters at once:

```php
$so = (new StructuredOutput())
    ->withMessages('Jason is 25 years old')
    ->withResponseClass(User::class)
    ->withSystem('Extract user data from the text.')
    ->withModel('gpt-4o')
    ->withMaxRetries(2);
// @doctest id="4d54"
```

Or equivalently:

```php
$so = (new StructuredOutput())->with(
    messages: 'Jason is 25 years old',
    responseModel: User::class,
    system: 'Extract user data from the text.',
    model: 'gpt-4o',
);
// @doctest id="b161"
```

### Executing and Retrieving Results

```php
// Get the parsed value directly
$user = $so->get();

// Get a typed scalar
$count = $so->getInt();

// Get the full response envelope (value + raw provider response)
$response = $so->response();

// Get only the raw inference response
$raw = $so->inferenceResponse();

// Stream partial updates
$stream = $so->stream();
// @doctest id="d380"
```

All of the above are shortcuts that internally call `create()` to obtain a
`PendingStructuredOutput`, then forward to the appropriate method.


## `StructuredOutputRuntime`

`StructuredOutputRuntime` assembles the runtime dependencies -- inference provider,
event dispatcher, configuration, and optional pipeline customizations (validators,
transformers, deserializers, extractors).

```php
use Cognesy\Instructor\StructuredOutputRuntime;

$runtime = StructuredOutputRuntime::fromConfig($llmConfig);
$runtime = StructuredOutputRuntime::fromDefaults();
$runtime = StructuredOutputRuntime::fromProvider($provider);
// @doctest id="5e0e"
```

### Event Listeners

The runtime owns the event dispatcher. Attach listeners here for logging,
monitoring, or debugging:

```php
$runtime
    ->onEvent(ResponseValidationFailed::class, fn($e) => logger()->warning($e))
    ->wiretap(fn($event) => $event->print());
// @doctest id="be60"
```

### Pipeline Customization

You can inject custom validators, transformers, deserializers, and extractors at the
runtime level. These apply to every request processed through the runtime:

```php
$runtime = $runtime
    ->withValidators([MyCustomValidator::class])
    ->withTransformers([MyTransformer::class]);
// @doctest id="9ef5"
```


## `PendingStructuredOutput`

`PendingStructuredOutput` is the lazy execution handle returned by `create()`. No
network call is made until you ask for a result. It coordinates one-shot access
across `get()`, `response()`, `inferenceResponse()`, and `stream()`.

```php
$pending = $so->create();

// Trigger execution and get the value
$value = $pending->get();

// Or inspect the full response
$response = $pending->response();

// Or access the raw inference response
$raw = $pending->inferenceResponse();

// Or stream partial updates
$stream = $pending->stream();
// @doctest id="6381"
```

The handle also provides typed accessors via the `HandlesResultTypecasting` trait:
`getString()`, `getInt()`, `getFloat()`, `getBoolean()`, `getArray()`, `getObject()`,
and `getInstanceOf(SomeClass::class)`.


## `StructuredOutputStream`

`StructuredOutputStream` exposes streaming reads when the request is executed with
streaming enabled. It provides several iteration modes:

```php
$stream = $so->stream();

// Iterate over partial parsed values
foreach ($stream->partials() as $partial) {
    echo $partial->name; // progressively updated
}

// Iterate over completed sequence items only
foreach ($stream->sequence() as $item) {
    // each $item is a fully completed Sequenceable
}

// Iterate over response snapshots (includes isPartial flag)
foreach ($stream->responses() as $response) {
    // $response->isPartial(), $response->value(), etc.
}

// Consume the stream and get the final value
$final = $stream->finalValue();

// Get the final response envelope
$finalResponse = $stream->finalResponse();
// @doctest id="0684"
```


## `StructuredOutputResponse`

`StructuredOutputResponse` is a read-only envelope that pairs the parsed value with
the raw provider response:

```php
$response = $so->response();

$response->value();          // the deserialized object or scalar
$response->inferenceResponse(); // InferenceResponse from the provider
$response->isPartial();      // false for final responses
$response->usage();          // token usage stats
$response->finishReason();   // stop, length, tool_calls, etc.
$response->content();        // raw content string
$response->toolCalls();      // tool call data (when using Tools mode)
// @doctest id="36ac"
```


## Error Handling

When a request fails validation or deserialization, the package uses its retry
mechanism (controlled by `maxRetries` in `StructuredOutputConfig`) to re-prompt the
LLM with error feedback. If all retries are exhausted, an exception is thrown.

Unrecoverable errors (e.g., network failures, missing response model) throw
immediately without retry.
