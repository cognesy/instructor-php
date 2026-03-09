---
title: Creating Requests
description: Build inference requests with explicit request fields.
meta:
  - name: 'has_code'
    content: true
---

Polyglot 2.0 builds requests from explicit fields instead of synthetic modes.

The main method is `with(...)`:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('openai')
    ->with(
        messages: [
            ['role' => 'system', 'content' => 'Answer briefly.'],
            ['role' => 'user', 'content' => 'What is CQRS?'],
        ],
        model: 'gpt-4.1-nano',
        options: ['temperature' => 0.2],
    )
    ->get();
```

## Use the Focused Helpers

Use the dedicated helpers when you want requests to stay readable:

- `withMessages(...)`
- `withModel(...)`
- `withTools(...)`
- `withToolChoice(...)`
- `withResponseFormat(...)`
- `withOptions(...)`

## Use `InferenceRequest` Directly

If your app already builds request objects, pass them in directly:

```php
<?php

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Inference;

$request = new InferenceRequest(
    messages: 'Return one deployment tip.',
    model: 'gpt-4.1-nano',
    options: ['temperature' => 0],
);

$text = Inference::using('openai')
    ->withRequest($request)
    ->get();
```
