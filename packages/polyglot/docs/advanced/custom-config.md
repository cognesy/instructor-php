---
title: Configuration Deep Dive
description: 'Learn how to configure different LLM providers and models in Polyglot.'
---

One of Polyglot's core strengths is its ability to work with multiple LLM providers through
a unified API. This chapter covers how to configure, manage, and switch between different
providers and models to get the most out of the library.



## Understanding Provider Configuration

Polyglot organizes provider settings through connection presets - named configurations that include
the details needed to communicate with a specific LLM provider. These connection presets are defined
in the configuration files and can be selected at runtime.

### The Configuration Files

The primary configuration files for Polyglot are:

1. **`config/llm.php`**: Contains configurations for LLM providers (chat/completion)
2. **`config/embed.php`**: Contains configurations for embedding providers

Let's focus on the structure of these configuration files.

#### LLM Configuration Structure

The `llm.php` configuration file has the following structure:

```php
<?php
use Cognesy\Config\Env;

return [
    // Default connection to use when none is specified
    'defaultPreset' => 'openai',

    // Connection preset definitions
    'presets' => [
        // OpenAI connection
        'openai' => [
            'providerType' => 'openai',
            'apiUrl' => 'https://api.openai.com/v1',
            'apiKey' => Env::get('OPENAI_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'metadata' => [
                'organization' => '',
                'project' => '',
            ],
            'model' => 'gpt-4o-mini',
            'maxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 16384,
        ],

        // Anthropic connection
        'anthropic' => [
            'providerType' => 'anthropic',
            'apiUrl' => 'https://api.anthropic.com/v1',
            'apiKey' => Env::get('ANTHROPIC_API_KEY', ''),
            'endpoint' => '/messages',
            'metadata' => [
                'apiVersion' => '2023-06-01',
                'beta' => 'prompt-caching-2024-07-31',
            ],
            'model' => 'claude-3-haiku-20240307',
            'maxTokens' => 1024,
            'contextLength' => 200_000,
            'maxOutputLength' => 8192,
        ],

        // Additional connections...
    ],
];
```

#### Embedding Configuration Structure

The `embed.php` configuration file follows a similar pattern:

```php
<?php
use Cognesy\Config\Env;

return [
    'defaultPreset' => 'openai',

    'presets' => [
        'openai' => [
            'providerType' => 'openai',
            'apiUrl' => 'https://api.openai.com/v1',
            'apiKey' => Env::get('OPENAI_API_KEY', ''),
            'endpoint' => '/embeddings',
            'metadata' => [
                'organization' => ''
            ],
            'model' => 'text-embedding-3-small',
            'defaultDimensions' => 1536,
            'maxInputs' => 2048,
        ],

        // Additional embedding connections...
    ],
];
```

### Connection Parameters

Each connection includes several parameters:

- **`providerType`**: The type of provider (OpenAI, Anthropic, etc.)
- **`apiUrl`**: The base URL for the provider's API
- **`apiKey`**: The API key for authentication
- **`endpoint`**: The specific API endpoint for chat completions or embeddings
- **`metadata`**: Additional provider-specific settings
- **`model`**: The default model to use
- **`maxTokens`**: Default maximum tokens for responses
- **`contextLength`**: Maximum context length supported by the model
- **`maxOutputLength`**: Maximum output length supported by the model
- **`httpClient`**: (Optional) Custom HTTP client to use


For embedding connections, the parameters are:

- **`providerType`**: The type of provider (OpenAI, Anthropic, etc.)
- **`apiUrl`**: The base URL for the provider's API
- **`apiKey`**: The API key for authentication
- **`endpoint`**: The specific API endpoint for chat completions or embeddings
- **`metadata`**: Additional provider-specific settings
- **`model`**: The default model to use
- **`defaultDimensions`**: The default dimensions of embedding vectors
- **`maxInputs`**: Maximum number of inputs that can be processed in a single request


## Connection preset name vs provider type

Configuration file `llm.php` contains a list of connection presets with the default names that might resemble
provider type names, but those are separate entities.

Provider type name refers to one of the supported LLM API providers and its underlying driver implementation,
either specific to this provider or a generic one - for example compatible with OpenAI ('openai-compatible').

Connection preset name refers to LLM API provider endpoint configuration with specific provider type, but also URL,
credentials, default model name, and default model parameter values.






## Managing API Keys

API keys should be stored securely and never committed to your codebase. Polyglot uses environment variables
for API keys.

### Setting Up Environment Variables

Create a `.env` file in your project root:

```bash
# OpenAI
OPENAI_API_KEY=sk-your-key-here

# Anthropic
ANTHROPIC_API_KEY=sk-ant-your-key-here

# Other providers
GEMINI_API_KEY=your-key-here
MISTRAL_API_KEY=your-key-here
COHERE_API_KEY=your-key-here
# etc.
```

Then load these environment variables using a package like `vlucas/phpdotenv`:

```php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
```

Or in frameworks like Laravel, environment variables are automatically loaded.

### Rotating API Keys

For better security, consider rotating your API keys regularly. You can update the environment
variables without changing your code.





## Provider-Specific Parameters

Different providers may support unique parameters and features. You can pass these as options to the
`create()` method.

### OpenAI-Specific Parameters

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = Inference::using('openai');

$response = $inference->with(
    messages: 'Generate a creative story.',
    options: [
        'temperature' => 0.8,         // Controls randomness (0.0 to 1.0)
        'top_p' => 0.95,              // Nucleus sampling parameter
        'frequency_penalty' => 0.5,   // Penalize repeated tokens
        'presence_penalty' => 0.5,    // Penalize repeated topics
        'stop' => ["\n\n", "THE END"],// Stop sequences
        'logit_bias' => [             // Adjust token probabilities
            // Token ID => bias value (-100 to +100)
            15043 => -100,  // Discourage a specific token
        ],
    ]
)->get();
```

### Anthropic-Specific Parameters

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = Inference::using('anthropic');

$response = $inference->with(
    messages: 'Generate a creative story.',
    options: [
        'temperature' => 0.7,
        'top_p' => 0.9,
        'top_k' => 40,               // Consider only the top 40 tokens
        'max_tokens' => 1000,
        'stop_sequences' => ["\n\nHuman:"],
        'system' => 'You are a creative storyteller who specializes in magical realism.',
    ]
)->get();
```





## Creating Custom Provider Configurations

You can create custom configurations for providers that aren't included in the default settings
or to modify existing ones.

### Modifying Configuration Files

You can edit the `config/llm.php` and `config/embed.php` files directly:

```php
// In config/llm.php
return [
    'defaultPreset' => 'custom_openai',

    'presets' => [
        'custom_openai' => [
            'providerType' => 'openai',
            'apiUrl' => 'https://custom.openai-proxy.com/v1',
            'apiKey' => Env::get('CUSTOM_OPENAI_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'model' => 'gpt-4-turbo',
            'maxTokens' => 2048,
            'contextLength' => 128_000,
            'maxOutputLength' => 16384,
            // HTTP client configuration is defined via HttpClientBuilder + InferenceRuntime
        ],

        // Other connections...
    ],
];
```

### Runtime Configuration

You can also create custom configurations at runtime using the `LLMConfig` class:

```php
<?php
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

// Create a custom configuration
$customConfig = new LLMConfig(
    apiUrl: 'https://api.openai.com/v1',
    apiKey: (string) getenv('OPENAI_API_KEY'),
    endpoint: '/chat/completions',
    model: 'gpt-4-turbo',
    maxTokens: 2048,
    contextLength: 128000,
    driver: 'openai'
);

// Use the custom configuration
$inference = Inference::fromRuntime(InferenceRuntime::fromConfig($customConfig));

$response = $inference->with(
    messages: 'What are the benefits of using custom configurations?'
)->get();

echo $response;
```









## Environment-Based Configuration

You might want to use different providers in different environments:

```php
<?php
// config/llm.php

use Cognesy\Config\Env;

$environment = Env::get('APP_ENV', 'production');

return [
    'defaultPreset' => $environment === 'production' ? 'openai' : 'ollama',

    'presets' => [
        'openai' => [
            'providerType' => 'openai',
            'apiUrl' => 'https://api.openai.com/v1',
            'apiKey' => Env::get('OPENAI_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'model' => 'gpt-4o-mini',
            'maxTokens' => 1024,
        ],

        'ollama' => [
            'providerType' => 'ollama',
            'apiUrl' => 'http://localhost:11434/v1',
            'apiKey' => '',
            'endpoint' => '/chat/completions',
            'model' => 'llama2',
            'maxTokens' => 1024,
            // Select appropriate HTTP client preset via HttpClientBuilder if needed
        ],

        // Other connections...
    ],
];
```




## Creating Custom Inference Drivers

In this example we will use an existing driver bundled with Polyglot (OpenAIDriver) as a base class
for our custom driver.

The driver can be any class that implements `CanHandleInference` interface.

```php
// we register new provider type - 'custom-driver'
LLM::registerDriver(
    'custom-driver',
    fn($config, $httpClient) => new class($config, $httpClient) extends OpenAIDriver {
        public function handle(InferenceRequest $request): HttpResponse {
            // some extra functionality to demonstrate our driver is being used
            echo ">>> Handling request...\n";
            return parent::handle($request);
        }
    }
);

// in configuration we use newly defined provider type - 'custom-driver'
$config = new LLMConfig(
    apiUrl: 'https://api.openai.com/v1',
    apiKey: (string) Env::get('OPENAI_API_KEY', ''),
    endpoint: '/chat/completions',
    model: 'gpt-4o-mini',
    maxTokens: 128,
    httpClient: 'guzzle',
    providerType: 'custom-driver',
);

// now we're calling inference using our configuration
$answer = Inference::fromRuntime(InferenceRuntime::fromConfig($config))
    ->with(
        messages: [['role' => 'user', 'content' => 'What is the capital of France']],
        options: ['max_tokens' => 64]
    )
    ->toText();
```

An alternative way of providing driver definition is via class-string:

```php
LLM::registerDriver('another-driver', AnotherDriver::class);
```
