---
title: 'Setup'
description: 'Setup of Polyglot in your PHP project'
---

This chapter will guide you through the initial steps of setting up and using Polyglot in your PHP project. We'll cover installation and configuration to get you up and running quickly.




## Installation

You can install it using Composer:

```bash
composer require cognesy/instructor-polyglot
```

This will install Polyglot along with its dependencies.


> NOTE: Polyglot is distributed as part of the Instructor PHP package, so if you have it installed, you don't need to install Polyglot separately.

## Requirements

- PHP 8.2 or higher
- Composer
- Valid API keys for at least one supported LLM provider




## Configuration

### Setting Up API Keys

Polyglot requires API keys to authenticate with LLM providers. The recommended approach is to use environment variables:

1. Create a `.env` file in your project root (or use your existing one)
2. Add your API keys:

```
# OpenAI
OPENAI_API_KEY=sk-your-openai-key

# Anthropic
ANTHROPIC_API_KEY=sk-ant-your-anthropic-key

# Other providers as needed
MISTRAL_API_KEY=your-mistral-key
GEMINI_API_KEY=your-gemini-key
# etc.
```

3. Make sure your application loads these environment variables (using a package like `vlucas/phpdotenv` or your framework's built-in environment handling)

### Configuration Files

Polyglot loads its configuration from PHP files. The default configuration files are located in the Instructor package, but you can publish and customize them:

1. Create a `config` directory in your project if it doesn't exist
2. Copy the configuration files from the Instructor package:

```bash
# Create config directory if it doesn't exist
mkdir -p config

# Copy configuration files
cp vendor/cognesy/instructor/config/llm.php config/
cp vendor/cognesy/instructor/config/embed.php config/
```

3. Customize the configuration files as needed

#### LLM Configuration

The `llm.php` configuration file contains settings for LLM providers:

```php
<?php
// Example of a simplified config/llm.php

use Cognesy\Utils\Env;

return [
    'defaultConnection' => 'openai',  // Default connection to use

    'connections' => [
        'openai' => [
            'providerType' => 'openai',
            'apiUrl' => 'https://api.openai.com/v1',
            'apiKey' => Env::get('OPENAI_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'defaultModel' => 'gpt-4o-mini',
            'defaultMaxTokens' => 1024,
        ],

        'anthropic' => [
            'providerType' => 'anthropic',
            'apiUrl' => 'https://api.anthropic.com/v1',
            'apiKey' => Env::get('ANTHROPIC_API_KEY', ''),
            'endpoint' => '/messages',
            'metadata' => [
                'apiVersion' => '2023-06-01',
            ],
            'defaultModel' => 'claude-3-haiku-20240307',
            'defaultMaxTokens' => 1024,
        ],

        // Other connections...
    ],
];
```

#### Embeddings Configuration

The `embed.php` configuration file contains settings for embeddings providers:

```php
<?php
// Example of a simplified config/embed.php

use Cognesy\Utils\Env;

return [
    'defaultConnection' => 'openai',

    'connections' => [
        'openai' => [
            'providerType' => 'openai',
            'apiUrl' => 'https://api.openai.com/v1',
            'apiKey' => Env::get('OPENAI_API_KEY', ''),
            'endpoint' => '/embeddings',
            'defaultModel' => 'text-embedding-3-small',
        ],

        // Other connections...
    ],
];
```

### Custom Configuration Location

By default, Polyglot looks for configuration files in the `config` directory relative to your project root. You can specify a different location by setting the `INSTRUCTOR_CONFIG_PATH` environment variable:

```
INSTRUCTOR_CONFIG_PATH=/path/to/your/config
```

### Overriding Configuration Location

You can use `Settings` class static `setPath()` method to override the value of config path set in environment variable with your own value.

```php
use Cognesy\Utils\Settings;

Settings::setPath('/your/path/to/config');
```


## Troubleshooting

### Common Installation Issues

- **Composer Dependencies**: Make sure you have PHP 8.2+ installed and Composer correctly configured.
- **API Keys**: Verify that your API keys are correctly set in your environment variables.
- **Configuration Files**: Check that your configuration files are properly formatted and accessible.

### Testing Your Installation

A simple way to test if everything is working correctly is to run a small script:

```php
<?php
require 'vendor/autoload.php';

use Cognesy\Polyglot\LLM\Inference;

try {
    $result = Inference::text('Say hello.');
    echo "Successfully received response: $result\n";
    echo "Polyglot is working correctly!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

If you see a friendly greeting, your installation is working correctly!

