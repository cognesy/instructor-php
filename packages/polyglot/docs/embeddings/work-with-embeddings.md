---
title: Working with Embeddings
description: Build requests, run them, and read vectors.
---

The common path is simple:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;

$response = Embeddings::using('openai')
    ->withInputs(['The quick brown fox'])
    ->get();

$vector = $response->first()?->values() ?? [];
```

## Multiple Inputs

```php
<?php

$response = Embeddings::using('openai')
    ->withInputs([
        'First document',
        'Second document',
    ])
    ->get();

$vectors = $response->toValuesArray();
```

## Response Helpers

`EmbeddingsResponse` exposes:

- `first()`
- `last()`
- `vectors()`
- `all()`
- `toValuesArray()`
- `usage()`
