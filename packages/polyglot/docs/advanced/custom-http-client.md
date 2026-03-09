---
title: Custom HTTP Client
description: Inject your own HTTP transport into a runtime.
---

Polyglot creates an HTTP client for you by default.
If your app already owns that concern, inject one into the runtime.

```php
<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$http = (new HttpClientBuilder())->create();

$runtime = InferenceRuntime::fromConfig(
    config: new LLMConfig(
        driver: 'openai',
        apiUrl: 'https://api.openai.com/v1',
        apiKey: (string) getenv('OPENAI_API_KEY'),
        endpoint: '/chat/completions',
        model: 'gpt-4.1-nano',
    ),
    httpClient: $http,
);

$text = Inference::fromRuntime($runtime)
    ->withMessages('Say hello.')
    ->get();
```

The same pattern works for `EmbeddingsRuntime`.
