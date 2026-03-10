---
title: Upgrading Polyglot
description: 'Polyglot 2.0 uses explicit request fields instead of output modes.'
---

Polyglot 2.0 is centered around explicit request fields.

The main migration points are:

- remove old output mode usage
- set `responseFormat` for native JSON or JSON schema
- set `tools` and `toolChoice` for tool calling
- use `stream()->deltas()` for streaming

## Response Model

Polyglot is now explicitly the raw inference layer.

- `InferenceResponse` is the final raw provider response
- streaming yields `PartialInferenceDelta`
- structured value ownership belongs to higher-level packages such as Instructor

If older code assumed that Polyglot streaming yielded accumulated partial response snapshots, update that code to work from deltas instead.

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

## Streaming Migration

Update old streaming code like this:

- replace partial-response iteration with `stream()->deltas()`
- assemble final raw output with `final()`
- move partial structured parsing to Instructor or your own delta accumulator
