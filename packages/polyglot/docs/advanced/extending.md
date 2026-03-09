---
title: Extending Polyglot
description: Register custom drivers or build a runtime with your own wiring.
---

Polyglot exposes two extension points:

- inference drivers through `InferenceDriverRegistry`
- embeddings drivers through `Embeddings::registerDriver(...)`

## Custom Inference Driver

```php
<?php

use App\Polyglot\AcmeInferenceDriver;
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$drivers = BundledInferenceDrivers::registry()
    ->withDriver('acme', AcmeInferenceDriver::class);
```

Pass that registry into `InferenceRuntime::fromConfig(...)`.

## Custom Embeddings Driver

```php
<?php

use App\Polyglot\AcmeEmbeddingsDriver;
use Cognesy\Polyglot\Embeddings\Embeddings;

Embeddings::registerDriver('acme', AcmeEmbeddingsDriver::class);
```

Inference drivers must implement `CanProcessInferenceRequest`.
Embeddings drivers must implement `CanHandleVectorization`.
