---
title: Request Options
description: Customize provider-native request fields safely.
---

`options` is the escape hatch for provider-specific request fields.

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('openai')
    ->withMessages('Write one short sentence about PHP.')
    ->withOptions([
        'temperature' => 0.2,
        'top_p' => 0.9,
    ])
    ->get();
```

## Prefer Dedicated Helpers for Common Behavior

Use these helpers instead of manually shaping the same values in `options`:

- `withStreaming(true)`
- `withMaxTokens(256)`
- `withRetryPolicy($policy)`
- `withResponseCachePolicy($policy)`

## Cached Context

Use `withCachedContext(...)` for stable context you want attached to later requests:

- shared messages
- shared tools
- shared tool choice
- shared response format

Drivers can use that context to map provider-native caching features when available.

## Retry Policy

Do not put `retryPolicy` inside `options`.
Retry is configured explicitly with `withRetryPolicy(...)` or on the request object.
