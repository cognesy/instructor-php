---
title: 'Tell Harness: Safe Transient Experiment'
docname: 'tell_harness_transient_experiment'
order: 4
id: 'd1104'
tags:
  - 'no-replay'
  - 'tell'
  - 'tell-harness'
  - 'transient'
  - 'workspace'
---
## Overview

A transient request compiles the selected durable conversation as context but
cannot publish objects, refs, or named-session updates. This lets an
application test an alternative prompt or implementation plan without
contaminating the conversation it may later resume.

## Example

```php
<?php
require 'examples/boot.php';
require_once dirname(__DIR__).'/Support.php';

use Cognesy\Tell\Composition\Standalone\Profile\StandaloneTellHost;
use Cognesy\Tell\Data\TellRequest;

$project = TellHarnessExample::project();

try {
    $tell = StandaloneTellHost::open($project);
    $tell->workspace()->initialize();
    $review = $tell->conversation('release-review');

    $review->send(TellRequest::prompt('Record the current release decision.'));
    $before = $review->history()->head;

    $prompt = 'Challenge that decision and describe the strongest alternative.';
    $experiment = $tell->run(
        TellRequest::prompt($prompt)
            ->conversation('release-review')
            ->transient(),
    );
    $after = $review->history()->head;

    echo "=== Transient Result ===\n";
    echo $experiment->text(), "\n";
    echo 'Transient: '.($experiment->isTransient() ? 'yes' : 'no')."\n";
    echo 'Conversation head unchanged: '.($before === $after ? 'yes' : 'no')
        ."\n";

    assert($experiment->isTransient(), 'Expected a transient result.');
    assert(
        $before === $after,
        'A transient run must not move the conversation head.',
    );
} finally {
    TellHarnessExample::remove($project);
}
?>
```

## Key Points

- `transient()` is an execution guarantee, not merely a display preference.
- Use an explicit `conversation()` selector to evaluate the exact durable
  context that a later durable request would receive.
- Traces may still record transient execution under Tell's separate trace
  privacy policy; durable conversation state remains unchanged.
