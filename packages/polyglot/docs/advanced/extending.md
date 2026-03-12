---
title: Extending Polyglot
description: Register custom drivers or build a runtime with your own wiring.
---

Polyglot ships with drivers for over 25 LLM providers and several embeddings providers. When
you need to integrate a provider that is not bundled -- or override the behavior of an existing
one -- the library exposes clean extension points for both inference and embeddings.


## Custom Inference Drivers

Inference drivers implement the `CanProcessInferenceRequest` interface, which defines three
methods:

```php
interface CanProcessInferenceRequest
{
    public function makeResponseFor(InferenceRequest $request): InferenceResponse;

    /** @return iterable<PartialInferenceDelta> */
    public function makeStreamDeltasFor(InferenceRequest $request): iterable;

    public function capabilities(?string $model = null): DriverCapabilities;
}
```

| Method | Purpose |
|---|---|
| `makeResponseFor()` | Send a synchronous request and return the complete response |
| `makeStreamDeltasFor()` | Send a streaming request and yield partial deltas |
| `capabilities()` | Report driver capabilities (tool calls, JSON mode, vision, etc.) |

### Registering a Driver Class

The simplest approach is to provide a class string. Polyglot will instantiate it with the
standard constructor signature `($config, $httpClient, $events)`:

```php
<?php

use App\Polyglot\AcmeInferenceDriver;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;

$drivers = BundledInferenceDrivers::registry()
    ->withDriver('acme', AcmeInferenceDriver::class);

$config = new LLMConfig(
    driver: 'acme',
    apiUrl: 'https://api.acme.com/v1',
    apiKey: (string) getenv('ACME_API_KEY'),
    endpoint: '/chat/completions',
    model: 'acme-large',
);

$text = Inference::fromConfig($config, drivers: $drivers)
    ->withMessages(Messages::fromString('Hello from Acme!'))
    ->get();
```

### Registering a Driver Factory

For more control over instantiation, pass a callable that receives `LLMConfig`,
`CanSendHttpRequests`, and `CanHandleEvents`, and returns a `CanProcessInferenceRequest`:

```php
<?php

use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIDriver;

$drivers = BundledInferenceDrivers::registry()
    ->withDriver('custom', function ($config, $httpClient, $events) {
        // Wrap an existing driver with extra behavior
        return new class($config, $httpClient, $events) extends OpenAIDriver {
            public function makeResponseFor($request): \Cognesy\Polyglot\Inference\Data\InferenceResponse {
                // Add logging, metrics, request transformation, etc.
                return parent::makeResponseFor($request);
            }
        };
    });
```

This factory approach is particularly useful when you want to extend an existing driver with
minimal code -- for example, adding request logging or custom headers to an OpenAI-compatible
endpoint.

### Using the Registry with InferenceRuntime

You can pass the driver registry directly when building a runtime:

```php
<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$runtime = InferenceRuntime::fromConfig(
    config: $config,
    drivers: $drivers,
);

$inference = Inference::fromRuntime($runtime);
```

Or use the `drivers` parameter on `Inference::fromConfig()` or `Inference::using()`:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('acme', drivers: $drivers)
    ->withMessages(Messages::fromString('Hello!'))
    ->get();
```

### Implementing a Full Driver

When building a driver from scratch, you will typically need to implement several adapter
components:

1. **Request Adapter** -- transforms `InferenceRequest` into the provider's HTTP request format
2. **Body Format** -- structures the request body according to the provider's API schema
3. **Message Format** -- converts Polyglot's message format to the provider's format
4. **Response Adapter** -- parses the provider's HTTP response into `InferenceResponse`
5. **Usage Format** -- extracts token usage information from the response

Most bundled drivers follow this modular adapter pattern. See the `OpenAIDriver` or
`AnthropicDriver` source code for reference implementations.


## Custom Embeddings Drivers

Embeddings drivers implement the `CanHandleVectorization` interface:

```php
interface CanHandleVectorization
{
    public function handle(EmbeddingsRequest $request): HttpResponse;
    public function fromData(array $data): ?EmbeddingsResponse;
}
```

Register a custom embeddings driver using the `BundledEmbeddingsDrivers` registry, the same
pattern used for inference drivers:

```php
<?php

use App\Polyglot\AcmeEmbeddingsDriver;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Creation\BundledEmbeddingsDrivers;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;

$drivers = BundledEmbeddingsDrivers::registry()
    ->withDriver('acme', AcmeEmbeddingsDriver::class);

$config = new EmbeddingsConfig(
    driver: 'acme',
    apiUrl: 'https://api.acme.com/v1',
    apiKey: (string) getenv('ACME_API_KEY'),
    endpoint: '/embeddings',
    model: 'acme-embed-v1',
    dimensions: 768,
    maxInputs: 100,
);

$embeddings = Embeddings::fromRuntime(
    EmbeddingsRuntime::fromConfig($config, drivers: $drivers)
);
```

Like inference drivers, you can also pass a callable factory instead of a class string:

```php
<?php

use App\Polyglot\AcmeEmbeddingsDriver;
use Cognesy\Polyglot\Embeddings\Creation\BundledEmbeddingsDrivers;

$drivers = BundledEmbeddingsDrivers::registry()
    ->withDriver('acme', function ($config, $httpClient, $events) {
        return new AcmeEmbeddingsDriver($config, $httpClient, $events);
    });
```

> **Note:** The `EmbeddingsDriverRegistry` is immutable -- each mutation returns a new instance,
> matching the same pattern as `InferenceDriverRegistry`.


## Removing or Replacing Bundled Drivers

The `InferenceDriverRegistry` is immutable -- each mutation returns a new instance. You can
remove a bundled driver or replace it entirely:

```php
<?php

use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;

// Remove a driver
$drivers = BundledInferenceDrivers::registry()
    ->withoutDriver('ollama');

// Replace a driver
$drivers = BundledInferenceDrivers::registry()
    ->withDriver('openai', MyCustomOpenAIDriver::class);
```


## Bundled Drivers

For reference, Polyglot bundles the following inference drivers:

| Driver Name | Class |
|---|---|
| `a21` | `A21Driver` |
| `anthropic` | `AnthropicDriver` |
| `azure` | `AzureDriver` |
| `bedrock-openai` | `BedrockOpenAIDriver` |
| `cerebras` | `CerebrasDriver` |
| `cohere` | `CohereV2Driver` |
| `deepseek` | `DeepseekDriver` |
| `fireworks` | `FireworksDriver` |
| `gemini` | `GeminiDriver` |
| `gemini-oai` | `GeminiOAIDriver` |
| `glm` | `GlmDriver` |
| `groq` | `GroqDriver` |
| `huggingface` | `HuggingFaceDriver` |
| `inception` | `InceptionDriver` |
| `meta` | `MetaDriver` |
| `minimaxi` | `MinimaxiDriver` |
| `mistral` | `MistralDriver` |
| `openai` | `OpenAIDriver` |
| `openai-responses` | `OpenAIResponsesDriver` |
| `openresponses` | `OpenResponsesDriver` |
| `openrouter` | `OpenRouterDriver` |
| `perplexity` | `PerplexityDriver` |
| `qwen` | `QwenDriver` |
| `sambanova` | `SambaNovaDriver` |
| `xai` | `XAiDriver` |
| `moonshot` | `OpenAICompatibleDriver` |
| `ollama` | `OpenAICompatibleDriver` |
| `openai-compatible` | `OpenAICompatibleDriver` |
| `together` | `OpenAICompatibleDriver` |

The full list is defined in `BundledInferenceDrivers::registry()`.

Bundled embeddings drivers include: `openai`, `azure`, `cohere`, `gemini`, `jina`, `mistral`,
and `ollama`.


## Listening to Events

Both `InferenceRuntime` and `EmbeddingsRuntime` dispatch events at key lifecycle points. You
can listen for specific events or wiretap all of them:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\Events\InferenceDriverBuilt;

$runtime = InferenceRuntime::fromConfig(LLMConfig::fromPreset('openai'));

// Listen for a specific event
$runtime->onEvent(InferenceDriverBuilt::class, function (InferenceDriverBuilt $event) {
    echo "Driver built: " . $event->payload['driverClass'] . "\n";
});

// Or listen to all events for debugging
$runtime->wiretap(function ($event) {
    error_log(get_class($event));
});

$response = Inference::fromRuntime($runtime)
    ->withMessages(Messages::fromString('Hello!'))
    ->get();
```
