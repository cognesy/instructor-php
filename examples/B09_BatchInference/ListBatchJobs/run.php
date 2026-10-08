---
title: 'List batch jobs with an opaque cursor'
docname: 'batch_inference_list_jobs'
id: 'b905'
tags:
  - 'batch-inference'
  - 'list-jobs'
  - 'offline'
---
## Overview

Listing observes one page at a time. The cursor binds to its provider,
connection scope, and page size.

## Example

```php
<?php
require 'examples/boot.php';
require __DIR__.'/../Support/DemoBatch.php';

use Examples\BatchInference\Support\DemoBatch;

$batches = DemoBatch::client();
$first = $batches->listJobs(limit: 2);
$ids = array_map(
    static fn ($job): string => $job->reference()->id()->toString(),
    iterator_to_array($first->jobs()),
);
$second = $batches->listJobs(limit: 2, cursor: $first->nextCursor());

if ($ids !== ['batch-example'] || $first->nextCursor() === null
    || iterator_count($second->jobs()) !== 0 || $second->nextCursor() !== null) {
    throw new RuntimeException('Batch listing cursor did not advance.');
}
echo "Listed one job; continuation page is empty\n";
?>
```
