---
title: 'Vector Storage and Bounded Retrieval'
docname: 'retrieval'
id: 'retrieval-basics'
tags:
  - 'retrieval'
  - 'vector-store'
  - 'rag'
---
## Overview

Store precomputed vectors, run a bounded similarity query, assemble cited RAG
context, and traverse the corpus independently with a forward-only scan cursor.
This example is deterministic and does not call an external model.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Context\ContextAssembler;
use Cognesy\Retrieval\Context\ContextBudget;
use Cognesy\Retrieval\Cursor\DocumentCursor;
use Cognesy\Retrieval\Data\ScanRequest;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Drivers\InMemory\InMemoryStore;
use Cognesy\Retrieval\Query\VectorQuery;
use Cognesy\Retrieval\Retrieval;

$store = new InMemoryStore();
$store->upsert(VectorDocuments::of(
    new VectorDocument(
        'claims',
        new Vector([1.0, 0.0]),
        content: 'Escalate claims above the local approval limit.',
    ),
    new VectorDocument(
        'billing',
        new Vector([0.0, 1.0]),
        content: 'Invoices are paid within thirty days.',
    ),
));

$pending = Retrieval::fromStore($store)
    ->withQuery(new VectorQuery(new Vector([1.0, 0.0]), maxResults: 2))
    ->pending();
$hits = $pending->get();
$context = (new ContextAssembler())->assemble(
    $hits,
    new ContextBudget(maxBytes: 500, maxTokens: 100, maxEvidence: 2),
);

echo $context->text . PHP_EOL;
assert($hits->first()?->id === 'claims');
assert($context->citations()['S1']->id === 'claims');

$seen = [];
$cursor = new DocumentCursor($store, new ScanRequest(pageSize: 1));
foreach ($cursor as $document) {
    $seen[] = $document->id;
}
assert($seen === ['claims', 'billing']);
?>
```
