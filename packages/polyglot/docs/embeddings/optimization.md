---
title: Optimization
description: Keep embeddings requests simple and efficient.
---

Two patterns matter most in practice:

## Batch Inputs

Send multiple texts in one request when the provider preset allows it:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;

$response = Embeddings::using('openai')
    ->withInputs([
        'Document one',
        'Document two',
    ])
    ->get();
```

## Configure Retries Explicitly

```php
<?php

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;
use Cognesy\Polyglot\Embeddings\Embeddings;

$response = Embeddings::using('openai')
    ->withInputs(['Document one'])
    ->withRetryPolicy(new EmbeddingsRetryPolicy(maxAttempts: 3))
    ->get();
```
