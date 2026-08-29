---
title: 'Tell Harness: Explicit Context Lifecycle'
docname: 'tell_harness_context_lifecycle'
order: 5
id: 'd1105'
tags:
  - 'no-replay'
  - 'tell'
  - 'tell-harness'
  - 'compaction'
  - 'workspace'
---
## Overview

Tell makes context lifecycle explicit. A caller can compact a selected
conversation into a provenance-linked summary, then clear only that selector
when it intentionally wants a new empty context. Neither operation deletes
immutable records.

## Example

```php
<?php
require 'examples/boot.php';
require_once dirname(__DIR__).'/Support.php';

use Cognesy\Tell\Tell;
use Cognesy\Tell\Data\TellRequest;

$project = TellHarnessExample::project();

try {
    $tell = Tell::open($project);
    $tell->workspace()->initialize();
    $review = $tell->conversation('release-review');

    $review->send(
        TellRequest::prompt('Record the release risks and an owner for each.'),
    );
    $compacted = $review->compact(
        TellRequest::prompt('Summarize the release review for the next turn.'),
        'Keep risks, owners, and unresolved decisions.',
    );
    $cleared = $review->clear();

    echo "=== Context Lifecycle ===\n";
    echo 'Compaction changed head: '
        .($compacted->details['changed'] ? 'yes' : 'no')."\n";
    echo 'Clear changed head: '.($cleared->changed() ? 'yes' : 'no')."\n";
    echo 'Conversation empty: '.($cleared->isEmpty() ? 'yes' : 'no')."\n";

    assert(
        $compacted->details['changed'] === true,
        'Expected compaction to publish a summary.',
    );
    assert($cleared->isEmpty(), 'Expected clear to select an empty conversation.');
} finally {
    TellHarnessExample::remove($project);
}
```

## Key Points

- Compaction uses the selected inference configuration; it is a deliberate
  model operation rather than an automatic hidden truncation.
- `clear()` moves only the selected conversation reference to empty.
- Immutable canonical records remain available to storage maintenance; clear
  is not deletion or garbage collection.
