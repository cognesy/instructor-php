---
title: Upgrading Polyglot
description: 'Migrate Polyglot raw inference code to the explicit 2.0 request shape.'
---

Polyglot 2.0 removes mode-based response shaping.
Polyglot 2.0 also removes snapshot-style partial streaming from its public API.

## Removed APIs

- `Cognesy\Polyglot\Inference\Enums\OutputMode`
- `Inference::withOutputMode(...)`
- `mode:` in `Inference::with(...)`
- `mode` on `InferenceRequest`
- `PartialInferenceResponseCreated`
- `PartialInferenceResponse` as the public stream payload
- `stream()->responses()`
- `stream()->onPartialResponse(...)`
- `stream()->partialResponse()`

## Use instead

Build raw inference requests from explicit provider-facing fields:

- `responseFormat` for native JSON object or JSON schema response formats
- `tools` for tool definitions
- `toolChoice` for tool selection
- `stream()->deltas()` for streaming chunks
- `stream()->onDelta(...)` / `stream()->lastDelta()` for stream observation

## Before

```php
<?php
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;

$data = Inference::using('openai')
    ->with(
        messages: 'Return JSON for Paris.',
        mode: OutputMode::Json,
    )
    ->asJsonData();
```

## After

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$data = Inference::using('openai')
    ->with(
        messages: 'Return JSON for Paris.',
        responseFormat: ['type' => 'json_object'],
    )
    ->asJsonData();
```

## Tool calls

If you want tool-call arguments as JSON, use:

```php
<?php
$args = Inference::using('openai')
    ->with(messages: 'Call the weather tool.', tools: $tools, toolChoice: 'auto')
    ->asToolCallJsonData();
```

Markdown-JSON fallback and other structured-output strategies now belong to Instructor, not Polyglot.
