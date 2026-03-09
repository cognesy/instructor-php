---
title: Upgrading Polyglot
description: 'Polyglot 2.0 uses explicit request fields instead of output modes.'
---

Polyglot 2.0 is centered around explicit request fields.

The important migration is simple:

- remove old output mode usage
- set `responseFormat` for native JSON or JSON schema
- set `tools` and `toolChoice` for tool calling
- use `stream()->deltas()` for streaming

## Before

```php
<?php

$data = $inference
    ->with(
        messages: 'Return JSON.',
        mode: $oldMode,
    )
    ->asJsonData();
```

## After

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$data = Inference::using('openai')
    ->with(
        messages: 'Return JSON.',
        responseFormat: ['type' => 'json_object'],
    )
    ->asJsonData();
```

Markdown-JSON fallback is no longer a Polyglot concern.
Use Instructor when you need higher-level structured output strategies.
