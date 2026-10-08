---
title: 'Native batch lifecycle'
docname: 'batch_inference_lifecycle'
id: 'b901'
tags:
  - 'batch-inference'
  - 'submit'
  - 'results'
  - 'offline'
---
## Overview

Submit one fixed input set, keep its reference, retrieve a later job snapshot,
and inspect both successful and failed items. This example uses an injected
deterministic driver and needs no API key.

## Example

```php
<?php
require 'examples/boot.php';
require __DIR__.'/../Support/DemoBatch.php';

use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Examples\BatchInference\Support\DemoBatch;

$batches = DemoBatch::client();
$submitted = $batches->submit(DemoBatch::items());
$reference = BatchReference::fromArray($submitted->reference()->toArray());
$observed = $batches->retrieve($reference);
$outcomes = iterator_to_array($batches->results($reference)->items());

if ($submitted->status() !== BatchStatus::Pending
    || $observed->status() !== BatchStatus::Completed
    || $outcomes[0]->key() !== 'row-1'
    || !$outcomes[0]->result()->isSuccess()
    || $outcomes[1]->key() !== 'row-2'
    || !$outcomes[1]->result()->isFailure()) {
    throw new RuntimeException('Unexpected batch lifecycle outcome.');
}
echo "Batch {$reference->id()->toString()}: 1 success, 1 item failure\n";
?>
```
