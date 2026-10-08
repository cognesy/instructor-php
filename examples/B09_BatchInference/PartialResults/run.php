---
title: 'Read partial batch results'
docname: 'batch_inference_partial_results'
id: 'b903'
tags:
  - 'batch-inference'
  - 'partial-results'
  - 'xai'
  - 'offline'
---
## Overview

xAI can publish results while a job is still running. This deterministic
example models two reads; the same key appears again, so a consumer should
upsert by its caller key.

## Example

```php
<?php
require 'examples/boot.php';
require __DIR__.'/../Support/DemoBatch.php';

use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Examples\BatchInference\Support\DemoBatch;

$batches = DemoBatch::client(partial: true);
$reference = $batches->submit(DemoBatch::items())->reference();
$first = $batches->results($reference);
$firstKeys = array_map(static fn ($item): string => $item->key(), iterator_to_array($first->items()));
$later = $batches->results($reference);
$laterKeys = array_map(static fn ($item): string => $item->key(), iterator_to_array($later->items()));

if ($first->availability() !== BatchResultsAvailability::Partial
    || $firstKeys !== ['row-1'] || $laterKeys !== ['row-1']) {
    throw new RuntimeException('Partial result reread behaved unexpectedly.');
}
echo "Partial result key row-1 appeared in both snapshots\n";
?>
```
