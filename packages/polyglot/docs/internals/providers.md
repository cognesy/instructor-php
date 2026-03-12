---
title: Providers
description: Provider objects are small config resolvers with optional explicit drivers.
---

Provider objects sit between configuration and runtime assembly. They resolve config values from presets, arrays, or explicit objects, and optionally carry an explicit driver instance. Runtimes use providers to determine which driver to build and how to configure it.


## LLMProvider

`LLMProvider` is a builder that wraps an `LLMConfig` and an optional explicit driver. It implements `CanResolveLLMConfig` and `HasExplicitInferenceDriver`, which the runtime uses during assembly.

**Namespace:** `Cognesy\Polyglot\Inference\LLMProvider`

### Creating a Provider

```php
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

// From a named preset
$provider = LLMProvider::using('openai');

// With a custom base path for presets
$provider = LLMProvider::using('openai', basePath: '/path/to/presets');

// From an explicit config
$provider = LLMProvider::fromLLMConfig($config);

// From an array
$provider = LLMProvider::fromArray([
    'driver' => 'anthropic',
    'apiUrl' => 'https://api.anthropic.com/v1',
    'apiKey' => getenv('ANTHROPIC_API_KEY'),
    'endpoint' => '/messages',
    'model' => 'claude-sonnet-4-20250514',
]);

// Default (OpenAI with gpt-4.1-nano)
$provider = LLMProvider::new();
```

### Customizing a Provider

All mutators return a new immutable instance:

```php
// Override specific config values
$provider = LLMProvider::using('openai')
    ->withModel('gpt-4.1')
    ->withConfigOverrides(['maxTokens' => 4096]);

// Replace the entire config
$provider = $provider->withLLMConfig($newConfig);

// Inject an explicit driver (bypasses the driver factory)
$provider = $provider->withDriver($customDriver);
```

When an explicit driver is set, the runtime uses it directly instead of building one from the config. This is useful for testing or for providers that need custom initialization.

### How the Runtime Uses It

When you call `InferenceRuntime::fromProvider($provider)`, the runtime:

1. Calls `$provider->resolveConfig()` to get the `LLMConfig`
2. Checks if `$provider->explicitInferenceDriver()` returns a driver
3. If an explicit driver exists, uses it directly
4. Otherwise, looks up the driver name from the config and creates one via the `InferenceDriverRegistry`


## EmbeddingsProvider

`EmbeddingsProvider` serves the same role for embeddings. It wraps an `EmbeddingsConfig` and an optional explicit driver.

**Namespace:** `Cognesy\Polyglot\Embeddings\EmbeddingsProvider`

### Creating a Provider

```php
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;

// Default (empty config)
$provider = EmbeddingsProvider::new();

// From an explicit config
$provider = EmbeddingsProvider::fromEmbeddingsConfig($config);

// From an array
$provider = EmbeddingsProvider::fromArray([
    'driver' => 'openai',
    'apiUrl' => 'https://api.openai.com/v1',
    'apiKey' => getenv('OPENAI_API_KEY'),
    'endpoint' => '/embeddings',
    'model' => 'text-embedding-3-small',
]);
```

Unlike `LLMProvider`, `EmbeddingsProvider` does not have a `using(...)` shortcut for presets. Use `Embeddings::using(...)` or construct the config explicitly.

### Customizing a Provider

```php
$provider = EmbeddingsProvider::fromArray([...])
    ->withConfigOverrides(['dimensions' => 256])
    ->withDriver($customDriver);
```


## Driver Factories

### Inference Driver Registry

The `InferenceDriverRegistry` manages the mapping between driver names and their factory callables. Polyglot ships with a default set of bundled drivers via `BundledInferenceDrivers::registry()`.

Supported inference drivers include:

| Driver Name | Class | Notes |
|---|---|---|
| `a21` | `A21Driver` | A21 Labs |
| `anthropic` | `AnthropicDriver` | Anthropic Messages API |
| `azure` | `AzureDriver` | Azure OpenAI |
| `bedrock-openai` | `BedrockOpenAIDriver` | AWS Bedrock (OpenAI-compatible) |
| `cerebras` | `CerebrasDriver` | Cerebras |
| `cohere` | `CohereV2Driver` | Cohere v2 |
| `deepseek` | `DeepseekDriver` | DeepSeek |
| `fireworks` | `FireworksDriver` | Fireworks AI |
| `gemini` | `GeminiDriver` | Google Gemini native API |
| `gemini-oai` | `GeminiOAIDriver` | Gemini via OpenAI-compatible endpoint |
| `glm` | `GlmDriver` | GLM |
| `groq` | `GroqDriver` | Groq |
| `huggingface` | `HuggingFaceDriver` | Hugging Face |
| `inception` | `InceptionDriver` | Inception |
| `meta` | `MetaDriver` | Meta Llama API |
| `minimaxi` | `MinimaxiDriver` | Minimaxi |
| `mistral` | `MistralDriver` | Mistral |
| `openai` | `OpenAIDriver` | OpenAI Chat Completions API |
| `openai-responses` | `OpenAIResponsesDriver` | OpenAI Responses API |
| `openresponses` | `OpenResponsesDriver` | Open Responses API |
| `openrouter` | `OpenRouterDriver` | OpenRouter |
| `perplexity` | `PerplexityDriver` | Perplexity |
| `qwen` | `QwenDriver` | Alibaba Qwen |
| `sambanova` | `SambaNovaDriver` | SambaNova |
| `xai` | `XAiDriver` | xAI (Grok) |
| `moonshot` | `OpenAICompatibleDriver` | Moonshot (via OpenAI-compatible) |
| `ollama` | `OpenAICompatibleDriver` | Ollama (via OpenAI-compatible) |
| `openai-compatible` | `OpenAICompatibleDriver` | Generic OpenAI-compatible APIs |
| `together` | `OpenAICompatibleDriver` | Together AI (via OpenAI-compatible) |

You can extend the registry with custom drivers:

```php
use Cognesy\Polyglot\Inference\Creation\InferenceDriverRegistry;
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;

$registry = BundledInferenceDrivers::registry()
    ->withDriver('my-provider', MyCustomDriver::class);

$runtime = InferenceRuntime::fromConfig($config, drivers: $registry);
```

A custom driver can be registered as a class name (must accept `LLMConfig`, `CanSendHttpRequests`, and `CanHandleEvents` in its constructor) or as a callable factory:

```php
$registry = $registry->withDriver('my-provider', function ($config, $httpClient, $events) {
    return new MyCustomDriver($config, $httpClient, $events);
});
```

You can also remove drivers from the registry:

```php
$registry = $registry->withoutDriver('openai-compatible');
```

### Embeddings Driver Registry

The `EmbeddingsDriverRegistry` follows the same immutable instance-based pattern as `InferenceDriverRegistry`. Bundled embeddings drivers are provided via `BundledEmbeddingsDrivers::registry()` and include: `openai`, `azure`, `cohere`, `gemini`, `jina`, `mistral`, and `ollama`.

Custom embeddings drivers can be registered through the registry:

```php
use Cognesy\Polyglot\Embeddings\Creation\BundledEmbeddingsDrivers;

$registry = BundledEmbeddingsDrivers::registry()
    ->withDriver('my-provider', MyEmbeddingsDriver::class);

$runtime = EmbeddingsRuntime::fromConfig($config, drivers: $registry);
```

Or with a factory callable:

```php
$registry = $registry->withDriver('my-provider', function ($config, $httpClient, $events) {
    return new MyEmbeddingsDriver($config, $httpClient, $events);
});
```

Both `InferenceDriverRegistry` and `EmbeddingsDriverRegistry` use immutable instance-based registration, so driver registrations can vary per runtime.


## Key Contracts

The provider system is built on a small set of interfaces:

### Provider Contracts

| Interface | Purpose |
|---|---|
| `CanResolveLLMConfig` | Returns an `LLMConfig` from a provider |
| `HasExplicitInferenceDriver` | Optionally returns a pre-built inference driver |
| `CanAcceptLLMConfig` | Allows setting an `LLMConfig` on a provider |
| `CanResolveEmbeddingsConfig` | Returns an `EmbeddingsConfig` from a provider |
| `HasExplicitEmbeddingsDriver` | Optionally returns a pre-built embeddings driver |

### Driver Contracts

| Interface | Purpose |
|---|---|
| `CanProcessInferenceRequest` | Main inference driver contract (make responses, stream deltas, report capabilities) |
| `CanHandleVectorization` | Main embeddings driver contract (handle requests, parse responses) |
| `CanProvideInferenceDrivers` | Registry that creates inference drivers by name |

### Adapter Contracts

| Interface | Purpose |
|---|---|
| `CanTranslateInferenceRequest` | Converts `InferenceRequest` to `HttpRequest` |
| `CanTranslateInferenceResponse` | Converts `HttpResponse` to `InferenceResponse` or stream deltas |
| `CanMapMessages` | Maps typed `Messages` to provider format |
| `CanMapRequestBody` | Assembles the request body |
| `CanMapUsage` | Extracts token usage from response data |

The driver contract `CanProcessInferenceRequest` also includes a `capabilities()` method that reports what features a driver supports (e.g., streaming, tool calls, structured output). This can be used to make runtime decisions about which features to use with a given provider:

```php
$driver->capabilities()->supportsStreaming;
$driver->capabilities('deepseek-reasoner')->supportsToolCalls;
```
