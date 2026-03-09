---
title: Custom Configuration
description: Build config objects when presets are not enough.
---

Use presets by default.
Use config objects when values are dynamic or app-generated.

## Inference Config

```php
<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;

$config = new LLMConfig(
    driver: 'openai',
    apiUrl: 'https://api.openai.com/v1',
    apiKey: (string) getenv('OPENAI_API_KEY'),
    endpoint: '/chat/completions',
    model: 'gpt-4.1-nano',
);

$text = Inference::fromConfig($config)
    ->withMessages('Say hello.')
    ->get();
```

## Embeddings Config

```php
<?php

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Embeddings;

$embeddings = Embeddings::fromConfig(new EmbeddingsConfig(
    driver: 'openai',
    apiUrl: 'https://api.openai.com/v1',
    apiKey: (string) getenv('OPENAI_API_KEY'),
    endpoint: '/embeddings',
    model: 'text-embedding-3-small',
));
```

## DSN Input

Both config types also support lightweight DSN loading through `fromDsn(...)`.
