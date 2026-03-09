---
title: Events
description: Runtime events are available for observability and debugging.
---

Inference and embeddings runtimes expose two event hooks:

- `onEvent($class, $listener)`
- `wiretap($listener)`

Example:

```php
<?php

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$runtime = InferenceRuntime::fromConfig(
    new LLMConfig(
        driver: 'openai',
        apiUrl: 'https://api.openai.com/v1',
        apiKey: (string) getenv('OPENAI_API_KEY'),
        endpoint: '/chat/completions',
        model: 'gpt-4.1-nano',
    ),
)->onEvent(InferenceResponseCreated::class, function ($event): void {
    // inspect event payload
});
```

Common inference events include:

- `InferenceStarted`
- `InferenceAttemptStarted`
- `InferenceResponseCreated`
- `PartialInferenceDeltaCreated`
- `InferenceCompleted`

Embeddings runtimes expose matching embeddings events such as `EmbeddingsRequested` and `EmbeddingsResponseReceived`.
