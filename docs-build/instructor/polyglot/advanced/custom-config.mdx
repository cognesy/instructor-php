---
title: Configuration Deep Dive
description: 'Learn how to configure different LLM providers and models in Polyglot.'
---

One of Polyglot's core strengths is its ability to work with multiple LLM providers through a unified API. This chapter covers how to configure, manage, and switch between different providers and models to get the most out of the library.




## Understanding Provider Configuration

Polyglot organizes provider settings through "connections" - named configurations that include all the details needed to communicate with a specific LLM provider. These connections are defined in the configuration files and can be selected at runtime.

### The Configuration Files

The primary configuration files for Polyglot are:

1. **`config/llm.php`**: Contains configurations for LLM providers (chat/completion)
2. **`config/embed.php`**: Contains configurations for embedding providers

Let's focus on the structure of these configuration files.

#### LLM Configuration Structure

The `llm.php` configuration file has the following structure:

```php
<?php
use Cognesy\Polyglot\LLM\Enums\LLMProviderType;
use Cognesy\Utils\Env;

return [
    // Default connection to use when none is specified
    'defaultConnection' => 'openai',

    // Default names for tools
    'defaultToolName' => 'extracted_data',
    'defaultToolDescription' => 'Function call based on user instructions.',

    // Default prompts for different modes
    'defaultRetryPrompt' => "JSON generated incorrectly, fix following errors:\n",
    'defaultMdJsonPrompt' => "Response must validate against this JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object within a ```json {} ``` codeblock.\n",
    'defaultJsonPrompt' => "Response must follow JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object.\n",
    'defaultToolsPrompt' => "Extract correct and accurate data from the input using provided tools.\n",

    // Connection definitions
    'connections' => [
        // OpenAI connection
        'openai' => [
            'providerType' => LLMProviderType::OpenAI->value,
            'apiUrl' => 'https://api.openai.com/v1',
            'apiKey' => Env::get('OPENAI_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'metadata' => [
                'organization' => '',
                'project' => '',
            ],
            'defaultModel' => 'gpt-4o-mini',
            'defaultMaxTokens' => 1024,
            'contextLength' => 128_000,
            'maxOutputLength' => 16384,
        ],

        // Anthropic connection
        'anthropic' => [
            'providerType' => LLMProviderType::Anthropic->value,
            'apiUrl' => 'https://api.anthropic.com/v1',
            'apiKey' => Env::get('ANTHROPIC_API_KEY', ''),
            'endpoint' => '/messages',
            'metadata' => [
                'apiVersion' => '2023-06-01',
                'beta' => 'prompt-caching-2024-07-31',
            ],
            'defaultModel' => 'claude-3-haiku-20240307',
            'defaultMaxTokens' => 1024,
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
use Cognesy\Polyglot\LLM\Enums\LLMProviderType;
use Cognesy\Utils\Env;

return [
    'debug' => [
        'enabled' => false,
    ],

    'defaultConnection' => 'openai',
    'connections' => [
        'openai' => [
            'providerType' => LLMProviderType::OpenAI->value,
            'apiUrl' => 'https://api.openai.com/v1',
            'apiKey' => Env::get('OPENAI_API_KEY', ''),
            'endpoint' => '/embeddings',
            'metadata' => [
                'organization' => ''
            ],
            'defaultModel' => 'text-embedding-3-small',
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
- **`defaultModel`**: The default model to use
- **`defaultMaxTokens`**: Default maximum tokens for responses
- **`contextLength`**: Maximum context length supported by the model
- **`maxOutputLength`**: Maximum output length supported by the model
- **`httpClient`**: (Optional) Custom HTTP client to use

For embedding connections, there are additional parameters:

- **`defaultDimensions`**: The default dimensions of embedding vectors
- **`maxInputs`**: Maximum number of inputs that can be processed in a single request




## Managing API Keys

API keys should be stored securely and never committed to your codebase. Polyglot uses environment variables for API keys.

### Setting Up Environment Variables

Create a `.env` file in your project root:

```
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

For better security, consider rotating your API keys regularly. You can update the environment variables without changing your code.





## Provider-Specific Parameters

Different providers may support unique parameters and features. You can pass these as options to the `create()` method.

### OpenAI-Specific Parameters

```php
<?php
use Cognesy\Polyglot\LLM\Inference;

$inference = new Inference('openai');

$response = $inference->create(
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
)->toText();
```

### Anthropic-Specific Parameters

```php
<?php
use Cognesy\Polyglot\LLM\Inference;

$inference = new Inference('anthropic');

$response = $inference->create(
    messages: 'Generate a creative story.',
    options: [
        'temperature' => 0.7,
        'top_p' => 0.9,
        'top_k' => 40,               // Consider only the top 40 tokens
        'max_tokens' => 1000,
        'stop_sequences' => ["\n\nHuman:"],
        'system' => 'You are a creative storyteller who specializes in magical realism.',
    ]
)->toText();
```



## Creating Custom Provider Configurations

You can create custom configurations for providers that aren't included in the default settings or to modify existing ones.

### Modifying Configuration Files

You can edit the `config/llm.php` and `config/embed.php` files directly:

```php
// In config/llm.php
return [
    'defaultConnection' => 'custom_openai',

    'connections' => [
        'custom_openai' => [
            'providerType' => LLMProviderType::OpenAI->value,
            'apiUrl' => 'https://custom.openai-proxy.com/v1',
            'apiKey' => Env::get('CUSTOM_OPENAI_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'gpt-4-turbo',
            'defaultMaxTokens' => 2048,
            'contextLength' => 128_000,
            'maxOutputLength' => 16384,
            'httpClient' => 'guzzle-custom', // Custom HTTP client configuration
        ],

        // Other connections...
    ],
];
```

### Runtime Configuration

You can also create custom configurations at runtime using the `LLMConfig` class:

```php
<?php
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Enums\LLMProviderType;

// Create a custom configuration
$customConfig = new LLMConfig(
    apiUrl: 'https://api.openai.com/v1',
    apiKey: getenv('OPENAI_API_KEY'),
    endpoint: '/chat/completions',
    model: 'gpt-4-turbo',
    maxTokens: 2048,
    contextLength: 128000,
    providerType: LLMProviderType::OpenAI->value
);

// Use the custom configuration
$inference = new Inference();
$inference->withConfig($customConfig);

$response = $inference->create(
    messages: 'What are the benefits of using custom configurations?'
)->toText();

echo $response;
```









## Environment-Based Configuration

You might want to use different providers in different environments:

```php
<?php
// config/llm.php

use Cognesy\Polyglot\LLM\Enums\LLMProviderType;
use Cognesy\Utils\Env;

$environment = Env::get('APP_ENV', 'production');

return [
    'defaultConnection' => $environment === 'production' ? 'openai' : 'ollama',

    'connections' => [
        'openai' => [
            'providerType' => LLMProviderType::OpenAI->value,
            'apiUrl' => 'https://api.openai.com/v1',
            'apiKey' => Env::get('OPENAI_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'gpt-4o-mini',
            'defaultMaxTokens' => 1024,
        ],

        'ollama' => [
            'providerType' => LLMProviderType::Ollama->value,
            'apiUrl' => 'http://localhost:11434/v1',
            'apiKey' => '',
            'endpoint' => '/chat/completions',
            'defaultModel' => 'llama2',
            'defaultMaxTokens' => 1024,
            'httpClient' => 'http-ollama',
        ],

        // Other connections...
    ],
];
```
