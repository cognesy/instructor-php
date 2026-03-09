---
title: Streaming Helpers
description: Additional helpers on `InferenceStream`.
---

`InferenceStream` also gives you a few convenience helpers around the delta stream:

- `onDelta(...)`
- `map(...)`
- `filter(...)`
- `reduce(...)`
- `all()`
- `lastDelta()`
- `usage()`
- `execution()`

Example:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('openai')
    ->withMessages('Write three short lines about queues.')
    ->withStreaming()
    ->stream()
    ->reduce(
        fn(string $carry, $delta) => $carry . $delta->contentDelta,
        '',
    );
```
