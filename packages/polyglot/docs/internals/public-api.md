---
title: Public API
description: The main classes applications are expected to use.
---

Most applications only need a small part of the package. The `Inference` and `Embeddings` facades provide a fluent, immutable interface that handles provider differences behind the scenes.


## Inference

The `Inference` class is the main entry point for LLM interactions. It encapsulates provider complexities behind a unified, fluent interface.

**Namespace:** `Cognesy\Polyglot\Inference\Inference`

### Creating an Instance

Polyglot offers several ways to create an `Inference` instance depending on how much control you need:

```php
use Cognesy\Polyglot\Inference\Inference;

// From a named preset (resolves config from YAML files)
$inference = Inference::using('openai');

// From an explicit config object
$inference = Inference::fromConfig($llmConfig);

// From a provider object (useful for overrides)
$inference = Inference::fromProvider($provider);

// From an already-built runtime
$inference = Inference::fromRuntime($runtime);
```

You can also pass a custom driver registry to `using()` or `fromConfig()` if you have registered custom drivers:

```php
$inference = Inference::using('my-provider', drivers: $customRegistry);
```

### Building a Request

All request methods return a new immutable instance, so you can safely branch configurations:

```php
$base = Inference::using('openai')
    ->withModel('gpt-4.1-nano')
    ->withMaxTokens(1024);

// Branch into two different requests from the same base
$response1 = $base->withMessages('Explain PHP traits.')->get();
$response2 = $base->withMessages('Explain PHP enums.')->get();
```

Available request methods:

| Method | Purpose |
|---|---|
| `with(...)` | Set multiple parameters at once (messages, model, tools, toolChoice, responseFormat, options) |
| `withMessages(...)` | Set the conversation messages |
| `withModel(...)` | Override the model |
| `withMaxTokens(...)` | Set maximum output tokens |
| `withTools(...)` | Provide tool/function definitions |
| `withToolChoice(...)` | Control tool selection behavior |
| `withResponseFormat(...)` | Request structured output format |
| `withOptions(...)` | Pass additional provider options (merged with existing) |
| `withStreaming(...)` | Enable or disable streaming |
| `withCachedContext(...)` | Set cached context (messages, tools, toolChoice, responseFormat) |
| `withRetryPolicy(...)` | Configure retry behavior via `InferenceRetryPolicy` |
| `withResponseCachePolicy(...)` | Control response caching via `ResponseCachePolicy` |
| `withRequest(...)` | Set all parameters from an existing `InferenceRequest` |
| `withRuntime(...)` | Swap the underlying runtime |

### Executing and Reading Results

Shortcuts execute the request and return results directly:

```php
// Get the text content
$text = $inference->withMessages('Hello')->get();

// Get the full response object
$response = $inference->withMessages('Hello')->response();

// Parse JSON from the response content
$data = $inference->withMessages('Return JSON')->asJsonData();

// Get JSON as a string
$json = $inference->withMessages('Return JSON')->asJson();

// Parse tool call arguments as JSON array
$args = $inference->withMessages('Call a tool')->asToolCallJsonData();

// Get tool call arguments as JSON string
$json = $inference->withMessages('Call a tool')->asToolCallJson();

// Stream the response
$stream = $inference->withMessages('Hello')->stream();
```

For lower-level control, `create()` returns a `PendingInference` without triggering execution:

```php
$pending = $inference->withMessages('Hello')->create();

// Then choose how to consume it
$text = $pending->get();
$response = $pending->response();
$stream = $pending->stream();
```

### Working with Responses

The `InferenceResponse` object provides access to all parts of the provider's response:

```php
$response = $inference->withMessages('Hello')->response();

$response->content();          // string -- the text content
$response->reasoningContent(); // string -- reasoning/thinking content (if supported)
$response->toolCalls();        // ToolCalls -- tool call collection
$response->usage();            // Usage -- token counts and optional cost
$response->finishReason();     // InferenceFinishReason enum
$response->responseData();     // HttpResponse -- raw provider response
$response->isPartial();        // bool -- true for partial streaming responses

// Convenience checks
$response->hasContent();
$response->hasReasoningContent();
$response->hasToolCalls();
$response->hasFinishReason();

// JSON extraction
$response->findJsonData();         // Json object from content
$response->findToolCallJsonData(); // Json object from tool call args
```

### Working with Streams

The `InferenceStream` provides several ways to consume streaming data:

```php
$stream = $inference->withMessages('Hello')->stream();

// Iterate over visible deltas
foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;          // incremental text
    echo $delta->reasoningContentDelta; // incremental reasoning (if any)
    echo $delta->toolName;              // tool name (if starting a tool call)
    echo $delta->toolArgs;              // tool arguments fragment
}

// Get the final assembled response
$finalResponse = $stream->final();

// Register a callback for each delta
$stream->onDelta(function (PartialInferenceDelta $delta): void {
    echo $delta->contentDelta;
});

// Functional-style processing
$texts = $stream->map(fn($d) => $d->contentDelta);
$full = $stream->reduce(fn($carry, $d) => $carry . $d->contentDelta, '');
$toolOnly = $stream->filter(fn($d) => $d->toolName !== '');

// Collect all deltas at once
$allDeltas = $stream->all();
```


## Embeddings

The `Embeddings` class provides a unified interface for generating vector embeddings across providers.

**Namespace:** `Cognesy\Polyglot\Embeddings\Embeddings`

### Creating an Instance

```php
use Cognesy\Polyglot\Embeddings\Embeddings;

// From a named preset
$embeddings = Embeddings::using('openai');

// From an explicit config
$embeddings = Embeddings::fromConfig($embeddingsConfig);

// From a provider object
$embeddings = Embeddings::fromProvider($provider);

// From a runtime
$embeddings = Embeddings::fromRuntime($runtime);
```

### Building a Request

```php
$embeddings = Embeddings::using('openai')
    ->withInputs('The quick brown fox')
    ->withModel('text-embedding-3-small')
    ->withOptions(['dimensions' => 256]);
```

Available request methods:

| Method | Purpose |
|---|---|
| `with(...)` | Set input, options, and model at once |
| `withInputs(...)` | Set input text(s) to embed (string or array of strings) |
| `withModel(...)` | Override the model |
| `withOptions(...)` | Pass additional provider options |
| `withRetryPolicy(...)` | Configure retry behavior via `EmbeddingsRetryPolicy` |
| `withRequest(...)` | Set all parameters from an existing `EmbeddingsRequest` |
| `withRuntime(...)` | Swap the underlying runtime |

### Executing and Reading Results

```php
// Get the full response
$response = $embeddings->withInputs('Hello world')->get();

// Get just the vector objects
$vectors = $embeddings->withInputs(['text one', 'text two'])->vectors();

// Get the first vector
$vector = $embeddings->withInputs('Hello world')->first();
```

For lower-level control, `create()` returns a `PendingEmbeddings`:

```php
$pending = $embeddings->withInputs('Hello world')->create();
$response = $pending->get();
```

### Working with Responses

The `EmbeddingsResponse` object provides access to the embedding vectors:

```php
$response = $embeddings->withInputs(['Hello', 'World'])->get();

$response->vectors();       // Vector[] -- all embedding vectors
$response->all();           // Vector[] -- alias for vectors()
$response->first();         // ?Vector -- first vector
$response->last();          // ?Vector -- last vector
$response->usage();         // Usage -- token counts
$response->toValuesArray(); // array -- raw float arrays

// Split vectors at a given index
[$before, $after] = $response->split(1);
```


## Registering Custom Drivers

### Inference Drivers

Custom inference drivers are registered through the `InferenceDriverRegistry` and passed to the runtime:

```php
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;

$registry = BundledInferenceDrivers::registry()
    ->withDriver('my-provider', MyCustomDriver::class);

$inference = Inference::using('my-provider', drivers: $registry);
```

### Embeddings Drivers

Custom embeddings drivers are registered through the `EmbeddingsDriverRegistry` and passed to the runtime:

```php
use Cognesy\Polyglot\Embeddings\Creation\BundledEmbeddingsDrivers;

$registry = BundledEmbeddingsDrivers::registry()
    ->withDriver('my-provider', MyCustomDriver::class);

$runtime = EmbeddingsRuntime::fromConfig($config, drivers: $registry);
$embeddings = Embeddings::fromRuntime($runtime);
```

See the [Providers](/internals/providers) page for details on driver registration and factory patterns.
