---
title: Context Caching
description: Attach stable context to later requests.
---

`withCachedContext(...)` lets you keep stable request parts outside the per-call message list.

That cached context can include:

- messages
- tools
- tool choice
- response format

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$inference = Inference::using('openai')->withCachedContext(
    messages: [
        ['role' => 'system', 'content' => 'Answer briefly.'],
        ['role' => 'user', 'content' => 'The topic is queue workers.'],
    ],
);

$text = $inference
    ->withMessages('Give me one practical tip.')
    ->get();
```

This is request-level context modeling.
If a provider reports cache usage, you can inspect it through `response()->usage()`.
