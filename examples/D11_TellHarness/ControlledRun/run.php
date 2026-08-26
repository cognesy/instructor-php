---
title: 'Tell Harness: Bound, Cancel, and Observe a Run'
docname: 'tell_harness_controlled_run'
order: 8
id: 'd1108'
tags:
  - 'no-replay'
  - 'tell'
  - 'tell-harness'
  - 'events'
  - 'cancellation'
---
## Overview

Put an explicit finite policy around production Tell work, stream completed
checkpoints, and send only the stable redacted event envelope across a process
boundary. The cancellation source can be retained by a web request, worker,
or supervisor; cancelling it cooperatively stops future work and prevents a
durable turn from publishing.

## Example

```php
<?php
require 'examples/boot.php';
require_once dirname(__DIR__).'/Support.php';

use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Tell\Tell;
use Cognesy\Tell\TellEvent;
use Cognesy\Tell\TellRequest;

$project = TellHarnessExample::project();
$cancellation = new InMemoryCancellationSource();

try {
    $tell = Tell::open($project, cancellation: $cancellation);
    $tell->workspace()->initialize();
    $stream = $tell->runStream(
        TellRequest::prompt('Inspect the project and report only actionable findings.')
            ->durable()
            ->maxSteps(5)
            ->maxRetries(1)
            ->timeoutMs(30_000)
            ->maxOutputChars(20_000)
            ->maxToolOutputChars(4_000)
            ->maxToolCalls(8)
            ->onEvent(static function (TellEvent $event): void {
                // Safe for queues, logs, server-sent events, and telemetry.
                echo json_encode($event->envelope(), JSON_THROW_ON_ERROR)."\n";
            }),
    );

    foreach ($stream as $checkpoint) {
        echo 'Completed steps: '.$checkpoint->stepCount()."\n";
    }
    $result = $stream->getReturn();

    // A supervisor can call this while the loop is running. A cancelled
    // durable run throws and leaves its selected workspace head unpublished.
    // $cancellation->cancel('Job deadline reached');

    assert($result->isCompleted(), 'Expected a bounded Tell run to complete.');
} finally {
    TellHarnessExample::remove($project);
}
```

## Key Points

- All limits are finite and are enforced across provider retries, output, and
  tool work. Tune them for the caller's own deadline and resource budget.
- Keep the cancellation source in the controlling process. Cancellation is
  cooperative, so currently executing provider or tool work stops at its next
  safe checkpoint.
- `TellEvent::envelope()` is the stable redacted boundary projection. Do not
  forward raw provider/framework event data to logs or clients.
