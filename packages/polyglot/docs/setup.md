---
title: 'Setup'
description: 'Setup of Polyglot in your PHP project'
meta:
  - name: 'has_code'
    content: true
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

- PHP 8.3 or higher
- Composer
- Valid API keys for at least one supported LLM provider




## Configuration

### Setting Up API Keys

Polyglot requires API keys to authenticate with LLM providers. The recommended approach is to use environment variables:

1. Create a `.env` file in your project root (or use your existing one)
2. Add your API keys:

```shell
# OpenAI
OPENAI_API_KEY=sk-your-openai-key

# Anthropic
ANTHROPIC_API_KEY=sk-ant-your-anthropic-key

# Other providers as needed
MISTRAL_API_KEY=your-mistral-key
GEMINI_API_KEY=your-gemini-key
# etc.
```

### Configuration Files

Polyglot loads configuration from package-scoped YAML files.

The default configuration files are located under package resources, for example:
- `packages/polyglot/resources/config/llm/default.yaml`
- `packages/polyglot/resources/config/llm/presets/*.yaml`
- `packages/polyglot/resources/config/embed/default.yaml`
- `packages/polyglot/resources/config/embed/presets/*.yaml`

You can publish and customize them:

1. Create a `config` directory in your project if it doesn't exist
2. Copy the configuration files from the Instructor package:

```bash
# Create config directory if it doesn't exist
mkdir -p config

# Copy polyglot YAML config trees
cp -r vendor/cognesy/instructor-php/packages/polyglot/resources/config/llm config/llm
cp -r vendor/cognesy/instructor-php/packages/polyglot/resources/config/embed config/embed
```

3. Customize the configuration files as needed


#### LLM Configuration

The `llm/presets/*.yaml` files contain provider presets. Example (`config/llm/presets/openai.yaml`):

```yaml
driver: openai
apiUrl: 'https://api.openai.com/v1'
apiKey: '${OPENAI_API_KEY}'
endpoint: /chat/completions
model: gpt-4.1-nano
maxTokens: 1024
```

#### Embeddings Configuration

The `embed/presets/*.yaml` files contain embeddings presets. Example (`config/embed/presets/openai.yaml`):

```yaml
driver: openai
apiUrl: 'https://api.openai.com/v1'
apiKey: '${OPENAI_API_KEY}'
endpoint: /embeddings
model: text-embedding-3-small
dimensions: 1536
maxInputs: 2048
```

### Custom Configuration Location

By default, Polyglot looks for custom configuration files in the `config` directory relative to your project root. You can specify a different location by setting the `INSTRUCTOR_CONFIG_PATHS` environment variable:

```shell
INSTRUCTOR_CONFIG_PATHS='/path/to/your/config,alternative/path'
```

### Overriding Configuration Location

Use explicit paths in your bootstrap to control configuration location.

```php
use Cognesy\Config\ConfigLoader;

$loader = ConfigLoader::fromPaths(
    __DIR__ . '/config/llm/presets/openai.yaml',
    __DIR__ . '/config/embed/presets/openai.yaml',
);
```


## Troubleshooting

### Common Installation Issues

- **Composer Dependencies**: Make sure you have PHP 8.3+ installed and Composer correctly configured.
- **API Keys**: Verify that your API keys are correctly set in your environment variables.
- **Configuration Files**: Check that your configuration files are properly formatted and accessible.


### Testing Your Installation

A simple way to test if everything is working correctly is to run a small script:

```php
<?php
require 'vendor/autoload.php';

use Cognesy\Polyglot\Inference\Inference;

$result = (new Inference)
    ->withMessages('Say hello.')
    ->get();
```

If you see a friendly greeting, your installation is working correctly!
