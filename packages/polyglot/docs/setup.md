---
title: Setup
description: 'Install Polyglot and configure presets.'
meta:
  - name: 'has_code'
    content: true
---

## Install

```bash
composer require cognesy/instructor-polyglot
```

Polyglot requires PHP 8.3+.

## Start with Bundled Presets

For many apps, the bundled presets are enough. Set the matching environment variable and call `using(...)`.

```bash
export OPENAI_API_KEY=...
export ANTHROPIC_API_KEY=...
export GEMINI_API_KEY=...
```

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('openai')
    ->withMessages('Say hello.')
    ->get();
```

## Override Presets in Your App

If you want app-owned presets, place YAML files in:

- `config/llm/presets`
- `config/embed/presets`

Example `config/llm/presets/openai.yaml`:

```yaml
driver: openai
apiUrl: 'https://api.openai.com/v1'
apiKey: '${OPENAI_API_KEY}'
endpoint: /chat/completions
model: gpt-4.1-nano
maxTokens: 1024
```

Example `config/embed/presets/openai.yaml`:

```yaml
driver: openai
apiUrl: 'https://api.openai.com/v1'
apiKey: '${OPENAI_API_KEY}'
endpoint: /embeddings
model: text-embedding-3-small
dimensions: 1536
maxInputs: 2048
```

Once the file exists, `Inference::using('openai')` and `Embeddings::using('openai')` will resolve it automatically.

## When You Need Runtime Config

Use config objects when values are dynamic:

```php
<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;

$inference = Inference::fromConfig(new LLMConfig(
    driver: 'openai',
    apiUrl: 'https://api.openai.com/v1',
    apiKey: (string) getenv('OPENAI_API_KEY'),
    endpoint: '/chat/completions',
    model: 'gpt-4.1-nano',
));
```
