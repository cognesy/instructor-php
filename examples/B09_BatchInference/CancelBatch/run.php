---
title: 'Request batch cancellation'
docname: 'batch_inference_cancel'
id: 'b904'
tags:
  - 'batch-inference'
  - 'cancel'
  - 'offline'
---
## Overview

Cancellation returns an acknowledgement, not proof of a cancelled final
state. Completion can win the race and its outcomes remain readable.

## Example

```php
<?php
require 'examples/boot.php';
require __DIR__.'/../Support/DemoBatch.php';

use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Examples\BatchInference\Support\DemoBatch;

$batches = DemoBatch::client();
$reference = $batches->submit(DemoBatch::items())->reference();
$receipt = $batches->cancel($reference);
$later = $batches->retrieve($reference);
$outcomes = iterator_to_array($batches->results($reference)->items());

if (!$receipt->acknowledged() || $receipt->alreadyTerminal()
    || $later->status() !== BatchStatus::Completed || count($outcomes) !== 2) {
    throw new RuntimeException('Cancellation race was not represented faithfully.');
}
echo "Cancellation acknowledged; later state completed with retained outcomes\n";
?>
```
