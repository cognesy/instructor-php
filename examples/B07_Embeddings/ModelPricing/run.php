---
title: 'Embedding model pricing'
docname: 'embedding_model_pricing'
id: 'a8e0'
tags:
  - 'embeddings'
  - 'model-catalog'
  - 'pricing'
  - 'offline'
---
## Overview

Estimate embedding cost from provider-reported input usage and the pricing
snapshot attached to an exact model record. Unknown paid usage returns no
estimate. An explicit zero rate remains known free and does not require a token
count.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsPricing;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsUsage;
use Cognesy\Polyglot\Embeddings\Models\ModelCatalog;
use Cognesy\Polyglot\Embeddings\Pricing\FlatRateCostCalculator;

$models = ModelCatalog::discover();
$model = $models->find('openai', 'text-embedding-3-small');
$pricing = $model->pricing;

if ($pricing === null) {
    throw new RuntimeException(
        'No reviewed pricing is available for this exact model.',
    );
}

$calculator = new FlatRateCostCalculator;
$reportedUsage = new EmbeddingsUsage(inputTokens: 25_000);
$cost = $calculator->calculate($reportedUsage, $pricing);

echo "Embedding pricing snapshot (USD per 1M input tokens):\n";
echo json_encode($pricing->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
echo 'Estimated cost: '.($cost?->toString() ?? 'unavailable')."\n\n";

$unavailable = $calculator->calculate(EmbeddingsUsage::none(), $pricing);
$knownFree = $calculator->calculate(
    EmbeddingsUsage::none(),
    new EmbeddingsPricing(inputPerMToken: 0),
);

echo 'Unknown paid input usage: '.($unavailable?->toString() ?? 'unavailable')."\n";
echo 'Explicitly free input rate: '.($knownFree?->toString() ?? 'unavailable')."\n";

assert($pricing->inputPerMToken === 0.02);
assert($cost?->total === 0.0005);
assert($unavailable === null);
assert($knownFree?->total === 0.0);
?>
```
