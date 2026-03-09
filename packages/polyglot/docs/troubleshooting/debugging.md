---
title: Debugging
description: Use runtime events and your own HTTP client to inspect requests.
---

The simplest debugging path is to attach listeners to the runtime.

```php
<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$runtime = InferenceRuntime::fromConfig(new LLMConfig(
    driver: 'openai',
    apiUrl: 'https://api.openai.com/v1',
    apiKey: (string) getenv('OPENAI_API_KEY'),
    endpoint: '/chat/completions',
    model: 'gpt-4.1-nano',
))->wiretap(function ($event): void {
    echo get_class($event) . PHP_EOL;
});

$text = Inference::fromRuntime($runtime)
    ->withMessages('Say hello.')
    ->get();
```

If you already own the HTTP client, inject it into the runtime and enable transport-level debugging there.
