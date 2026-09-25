---
title: 'Embedding model metadata'
docname: 'embedding_model_metadata'
id: '007e'
tags:
  - 'embeddings'
  - 'model-catalog'
  - 'offline'
---
## Overview

Read reviewed embedding limits by the exact `(driver, wire model)` route. Input
count, per-input tokens, total request tokens, and default output dimensions are
separate model facts. They support application planning; only limits enforced by
the runtime are automatic preflight checks.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Embeddings\Models\ModelCatalog;

$models = ModelCatalog::discover();
$model = $models->find('openai', 'text-embedding-3-small');

echo "EMBEDDING MODEL FACTS\n";
echo json_encode($model->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n\n";

// Approximate counts supplied by application-owned token estimation.
$inputCount = 2_000;
$largestInputTokens = 7_500;
$requestTokens = 310_000;

$inputCountFits = $model->maxInputs !== null
    && $inputCount <= $model->maxInputs;
$largestInputFits = $model->maxInputTokens !== null
    && $largestInputTokens <= $model->maxInputTokens;
$requestFits = $model->maxRequestTokens !== null
    && $requestTokens <= $model->maxRequestTokens;

echo 'Input count fits: '.($inputCountFits ? 'yes' : 'no')."\n";
echo 'Largest input fits: '.($largestInputFits ? 'yes' : 'no')."\n";
echo 'Whole request fits: '.($requestFits ? 'yes' : 'no')."\n";
echo 'Default vector dimensions: '.($model->defaultDimensions ?? 'unknown')."\n\n";

$unknown = $models->find('openai', 'text-embedding-preview');
echo 'Unknown preview dimensions: '.($unknown->defaultDimensions ?? 'unknown')."\n";

assert($model->maxInputs === 2_048);
assert($model->maxInputTokens === 8_192);
assert($model->maxRequestTokens === 300_000);
assert($model->defaultDimensions === 1_536);
assert($inputCountFits === true);
assert($largestInputFits === true);
assert($requestFits === false);
assert($unknown->defaultDimensions === null);
?>
```
