---
title: 'Run a provider batch lifecycle explicitly'
docname: 'batch_inference_live'
id: 'b908'
tags:
  - 'batch-inference'
  - 'live'
  - 'no-replay'
---
## Overview

The adjacent `live.php` command has separate submit, status, results, cancel,
and list actions. It requires `--live` and persists a credential-free
reference. Status and results observe once; they do not wait for completion.
Cancellation is an alternate branch, not a cleanup action after a successful
result read.

```sh
php examples/B09_BatchInference/LiveBatchLifecycle/live.php submit --provider=mistral --live --state=/tmp/polyglot-batch-reference.json
php examples/B09_BatchInference/LiveBatchLifecycle/live.php status --provider=mistral --live --state=/tmp/polyglot-batch-reference.json
php examples/B09_BatchInference/LiveBatchLifecycle/live.php results --provider=mistral --live --state=/tmp/polyglot-batch-reference.json
php examples/B09_BatchInference/LiveBatchLifecycle/live.php cancel --provider=mistral --live --state=/tmp/polyglot-batch-reference.json
php examples/B09_BatchInference/LiveBatchLifecycle/live.php list --provider=mistral --live --limit=10
```

## Offline example

```php
<?php
require 'examples/boot.php';
require __DIR__.'/../Support/DemoBatch.php';

use Examples\BatchInference\Support\DemoBatch;

$batches = DemoBatch::client();
$reference = $batches->submit(DemoBatch::items())->reference();
$observed = $batches->retrieve($reference);
if ($observed->reference()->id()->toString() !== $reference->id()->toString()) {
    throw new RuntimeException('Batch identity changed across observations.');
}
echo "Live commands are opt-in; offline lifecycle verified\n";
?>
```
