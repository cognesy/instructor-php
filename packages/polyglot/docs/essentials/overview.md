---
title: Overview of Inference
description: Quick, practical usage of the `Inference` facade.
---

`Inference` is the main facade for raw model responses.

Most code follows one small pattern:

1. resolve a preset or runtime
2. add messages
3. optionally shape the response
4. execute

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('openai')
    ->withMessages('Summarize event sourcing in two sentences.')
    ->get();
```

## Core Request Fields

- `messages`
- `model`
- `tools`
- `toolChoice`
- `responseFormat`
- `options`

## Core Execution Paths

- `get()` for text
- `response()` for `InferenceResponse`
- `asJsonData()` for decoded JSON
- `stream()` for `InferenceStream`

## Builder Style

`Inference` is immutable from the caller's point of view. Each `with...()` call returns a new configured instance.
