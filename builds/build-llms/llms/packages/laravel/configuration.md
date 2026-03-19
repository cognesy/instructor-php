# Configuration

After publishing the configuration file with `php artisan vendor:publish --tag=instructor-config`, you will find it at `config/instructor.php`. This file controls every aspect of the package, from LLM provider connections and extraction behavior to HTTP transport, logging, event bridging, and response caching.

This is Laravel-native configuration. The Laravel integration reads `config('instructor.*')` through Laravel's config repository and converts those arrays into typed runtime config objects internally. It does not ask the standalone `packages/config` YAML loader to parse `config/instructor.php`.

## Default Connection

```php
'default' => env('INSTRUCTOR_CONNECTION', 'openai'),
// @doctest id="bd42"
```

This determines which LLM connection is used when you call a facade without specifying one explicitly. You can override it at runtime with `->connection('name')` on any facade, or by passing an `LLMConfig` object via `->fromConfig(...)`.

## Connections

Configure multiple LLM provider connections. Each connection defines its driver, API credentials, default model, and token limits. You can define as many connections as you need and switch between them at runtime.

```php
'connections' => [
    'openai' => [
        'driver' => 'openai',
        'api_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1'),
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'max_tokens' => env('OPENAI_MAX_TOKENS', 4096),
    ],

    'anthropic' => [
        'driver' => 'anthropic',
        'api_url' => env('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1'),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 4096),
    ],

    'azure' => [
        'driver' => 'azure',
        'api_key' => env('AZURE_OPENAI_API_KEY'),
        'resource_name' => env('AZURE_OPENAI_RESOURCE'),
        'deployment_id' => env('AZURE_OPENAI_DEPLOYMENT'),
        'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-08-01-preview'),
        'model' => env('AZURE_OPENAI_MODEL', 'gpt-4o-mini'),
        'max_tokens' => env('AZURE_OPENAI_MAX_TOKENS', 4096),
    ],

    'gemini' => [
        'driver' => 'gemini',
        'api_url' => env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'max_tokens' => env('GEMINI_MAX_TOKENS', 4096),
    ],

    'ollama' => [
        'driver' => 'ollama',
        'api_url' => env('OLLAMA_API_URL', 'http://localhost:11434/v1'),
        'api_key' => env('OLLAMA_API_KEY', 'ollama'),
        'model' => env('OLLAMA_MODEL', 'llama3.2'),
        'max_tokens' => env('OLLAMA_MAX_TOKENS', 4096),
    ],
],
// @doctest id="f930"
```

### Supported Drivers

| Driver | Provider | Description |
|--------|----------|-------------|
| `openai` | OpenAI | GPT-4, GPT-4o, GPT-4o-mini |
| `anthropic` | Anthropic | Claude 3, Claude 3.5, Claude 4 |
| `azure` | Azure OpenAI | Azure-hosted OpenAI models |
| `gemini` | Google | Gemini 1.5, Gemini 2.0 |
| `mistral` | Mistral AI | Mistral, Mixtral models |
| `groq` | Groq | Fast inference with Llama, Mixtral |
| `cohere` | Cohere | Command models |
| `deepseek` | DeepSeek | DeepSeek models |
| `ollama` | Ollama | Local open-source models |
| `perplexity` | Perplexity | Perplexity models |

### Adding a Custom Connection

Any OpenAI-compatible API can be used by setting the `openai` driver and pointing `api_url` to your endpoint. Extra keys beyond the standard set (`driver`, `api_url`, `api_key`, `endpoint`, `model`, `max_tokens`, `options`) are automatically merged into the options array and forwarded with each request.

```php
'connections' => [
    // ... existing connections

    'my-custom' => [
        'driver' => 'openai', // Use OpenAI-compatible API
        'api_url' => 'https://my-custom-api.com/v1',
        'api_key' => env('MY_CUSTOM_API_KEY'),
        'model' => 'custom-model',
        'max_tokens' => 4096,
    ],
],
// @doctest id="84c9"
```

## Embeddings Connections

Configure embedding model connections separately from inference connections. The embeddings section has its own `default` key and connection definitions.

```php
'embeddings' => [
    'default' => env('INSTRUCTOR_EMBEDDINGS_CONNECTION', 'openai'),

    'connections' => [
        'openai' => [
            'driver' => 'openai',
            'api_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1'),
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_EMBEDDINGS_MODEL', 'text-embedding-3-small'),
            'dimensions' => env('OPENAI_EMBEDDINGS_DIMENSIONS', 1536),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'api_url' => env('OLLAMA_API_URL', 'http://localhost:11434/v1'),
            'api_key' => env('OLLAMA_API_KEY', 'ollama'),
            'model' => env('OLLAMA_EMBEDDINGS_MODEL', 'nomic-embed-text'),
            'dimensions' => env('OLLAMA_EMBEDDINGS_DIMENSIONS', 768),
        ],
    ],
],
// @doctest id="bf92"
```

## Extraction Settings

Configure defaults for structured output extraction. These values apply to every `StructuredOutput` call unless overridden at runtime.

```php
'extraction' => [
    // Output mode: json_schema, json, tools, md_json
    'output_mode' => env('INSTRUCTOR_OUTPUT_MODE', 'json_schema'),

    // Maximum retry attempts when validation fails
    'max_retries' => env('INSTRUCTOR_MAX_RETRIES', 2),

    // Prompt template for retry attempts
    'retry_prompt' => 'The response did not pass validation. Please fix the following errors and try again: {errors}',
],
// @doctest id="a001"
```

### Output Modes

The output mode controls how the package instructs the LLM to produce structured output. Different providers have varying levels of support for each mode.

| Mode | Description | Best For |
|------|-------------|----------|
| `json_schema` | Uses JSON Schema for structured output | Most reliable; recommended for OpenAI |
| `json` | Simple JSON mode without schema enforcement | Fallback for models that lack schema support |
| `tools` | Uses tool/function calling to extract structured data | Alternative approach; good cross-provider support |
| `md_json` | Markdown-wrapped JSON | Useful for Gemini and similar models |

## HTTP Client Settings

Configure the underlying HTTP transport. The Laravel package ships with its own `LaravelDriver` that wraps Laravel's HTTP client (`Illuminate\Http\Client\Factory`), which means `Http::fake()` works transparently in your tests.

```php
'http' => [
    // Driver: 'laravel' uses Laravel's HTTP client (enables Http::fake())
    'driver' => env('INSTRUCTOR_HTTP_DRIVER', 'laravel'),

    // Request timeout in seconds
    'timeout' => env('INSTRUCTOR_HTTP_TIMEOUT', 120),

    // Connection timeout in seconds
    'connect_timeout' => env('INSTRUCTOR_HTTP_CONNECT_TIMEOUT', 30),
],
// @doctest id="83d4"
```

The service provider binds `Cognesy\Http\Contracts\CanSendHttpRequests` to the Laravel-backed HTTP transport. All higher-level services (Inference, Embeddings, StructuredOutput) depend on that contract, ensuring consistent HTTP behavior across the entire package.

## Logging Settings

The package includes a logging pipeline that enriches log entries with Laravel request context (request ID, authenticated user, route, URL) automatically.

```php
'logging' => [
    // Enable/disable logging
    'enabled' => env('INSTRUCTOR_LOGGING_ENABLED', true),

    // Log channel (must exist in config/logging.php)
    'channel' => env('INSTRUCTOR_LOG_CHANNEL', 'stack'),

    // Minimum log level
    'level' => env('INSTRUCTOR_LOG_LEVEL', 'warning'),

    // Logging preset: default, production, or custom
    'preset' => env('INSTRUCTOR_LOGGING_PRESET', 'production'),

    // Events to exclude from logging
    'exclude_events' => [
        Cognesy\Http\Events\DebugRequestBodyUsed::class,
        Cognesy\Http\Events\DebugResponseBodyReceived::class,
    ],
],
// @doctest id="a2ae"
```

### Logging Presets

| Preset | Description |
|--------|-------------|
| `default` | Verbose logging suitable for local development; includes message templates for key events and excludes debug-level HTTP body events |
| `production` | Minimal logging at `warning` level and above; excludes verbose HTTP and partial-response events for lower overhead |
| `custom` | Fully configurable pipeline -- supply your own `channel`, `level`, `exclude_events`, `include_events`, and `templates` arrays |

Both the `default` and `production` presets automatically attach lazy enrichers that add the current HTTP request context (request ID, user ID, session ID, route, method, URL) to every log record.

## Events Settings

Configure how Instructor's internal events are bridged to Laravel's event dispatcher.

```php
'events' => [
    // Bridge Instructor events to Laravel's event dispatcher
    'dispatch_to_laravel' => env('INSTRUCTOR_DISPATCH_EVENTS', true),

    // Specific events to bridge (empty = all events)
    'bridge_events' => [
        // \Cognesy\Instructor\Events\ExtractionComplete::class,
    ],
],
// @doctest id="08d6"
```

When `bridge_events` is empty (the default), every Instructor event is forwarded to Laravel. To limit traffic, list only the event classes you care about. See the [Events](events.md) guide for the full list of available events and listener examples.

## Cache Settings

Configure response caching to avoid redundant API calls for identical inputs.

```php
'cache' => [
    // Enable response caching
    'enabled' => env('INSTRUCTOR_CACHE_ENABLED', false),

    // Cache store to use (null = default store)
    'store' => env('INSTRUCTOR_CACHE_STORE'),

    // Default TTL in seconds
    'ttl' => env('INSTRUCTOR_CACHE_TTL', 3600),

    // Cache key prefix
    'prefix' => 'instructor',
],
// @doctest id="2068"
```

## Environment Variables Reference

| Variable | Default | Description |
|----------|---------|-------------|
| `INSTRUCTOR_CONNECTION` | `openai` | Default LLM connection |
| `INSTRUCTOR_OUTPUT_MODE` | `json_schema` | Output mode for extraction |
| `INSTRUCTOR_MAX_RETRIES` | `2` | Max validation retry attempts |
| `INSTRUCTOR_HTTP_DRIVER` | `laravel` | HTTP client driver |
| `INSTRUCTOR_HTTP_TIMEOUT` | `120` | Request timeout (seconds) |
| `INSTRUCTOR_HTTP_CONNECT_TIMEOUT` | `30` | Connection timeout (seconds) |
| `INSTRUCTOR_LOGGING_ENABLED` | `true` | Enable logging |
| `INSTRUCTOR_LOG_CHANNEL` | `stack` | Laravel log channel |
| `INSTRUCTOR_LOG_LEVEL` | `warning` | Minimum log level |
| `INSTRUCTOR_LOGGING_PRESET` | `production` | Logging preset |
| `INSTRUCTOR_DISPATCH_EVENTS` | `true` | Bridge events to Laravel |
| `INSTRUCTOR_CACHE_ENABLED` | `false` | Enable response caching |
| `OPENAI_API_KEY` | -- | OpenAI API key |
| `ANTHROPIC_API_KEY` | -- | Anthropic API key |

## Runtime Configuration

Override any configuration at runtime using the fluent API on the facades:

```php
use Cognesy\Instructor\Laravel\Facades\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;

$result = StructuredOutput::connection('anthropic')  // Switch connection
    ->withModel('claude-3-opus-20240229')        // Override model
    ->withRuntime(
        StructuredOutputRuntime::fromDefaults()->withMaxRetries(5)
    )                                            // Override retries
    ->with(
        messages: 'Extract data...',
        responseModel: MyModel::class,
    )
    ->get();
// @doctest id="2763"
```

For full programmatic control, build an `LLMConfig` object and pass it directly:

```php
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$config = LLMConfig::fromArray([
    'driver' => 'openai',
    'apiUrl' => 'https://api.openai.com/v1',
    'apiKey' => $myKey,
    'model' => 'gpt-4o',
    'maxTokens' => 8192,
]);

$result = StructuredOutput::fromConfig($config)
    ->with(messages: '...', responseModel: MyModel::class)
    ->get();
// @doctest id="3639"
```
