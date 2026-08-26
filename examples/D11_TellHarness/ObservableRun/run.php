---
title: 'Tell Harness: Observe a Long-Running Run'
docname: 'tell_harness_observable_run'
order: 2
id: 'd1102'
tags:
  - 'no-replay'
  - 'tell'
  - 'tell-harness'
  - 'events'
  - 'streaming'
---
## Overview

`runStream()` gives a PHP caller completed agent-loop checkpoints, while
`onEvent()` receives lifecycle observations in source order. This is the Tell
surface for worker progress, server-sent events, telemetry, and tool/inference
debugging; it does not require parsing TOON, NDJSON, or a trace file.

Consume the generator fully before `getReturn()`. In a durable run this is also
the point at which Tell is allowed to publish the new conversation head.

## Example

```php
<?php
require 'examples/boot.php';
require_once dirname(__DIR__).'/Support.php';

use Cognesy\Tell\Tell;
use Cognesy\Tell\TellEvent;
use Cognesy\Tell\TellRequest;

$project = TellHarnessExample::project();
$eventTypes = [];

try {
    $tell = Tell::open($project);
    $stream = $tell->runStream(
        TellRequest::prompt(
            'Inspect the available context, use a tool if one is useful, then '
            .'give a concise report.',
        )
            ->maxSteps(5)
            ->onEvent(
                static function (TellEvent $event) use (&$eventTypes): void {
                    $eventTypes[] = $event->type();
                    echo json_encode($event->envelope(), JSON_THROW_ON_ERROR)."\n";
                },
            ),
    );

    foreach ($stream as $progress) {
        echo sprintf(
            '[checkpoint] steps=%d tools=%s status=%s%s',
            $progress->stepCount(),
            $progress->hasToolCalls() ? 'yes' : 'no',
            $progress->status()?->value ?? 'unknown',
            PHP_EOL,
        );
    }
    $result = $stream->getReturn();

    echo "\n=== Tell Result ===\n";
    echo $result->text(), "\n";
    echo 'Observed events: '.count($eventTypes)."\n";

    assert(
        $result->isCompleted(),
        'Expected a completed streamed Tell result.',
    );
    assert($eventTypes !== [], 'Expected at least one Tell lifecycle event.');
} finally {
    TellHarnessExample::remove($project);
}
```

## Key Points

- A checkpoint represents a completed agent-loop step, not an arbitrary token.
- `TellEvent::envelope()` is the stable, redacted event contract for logs,
  queues, server-sent events, and telemetry.
- Treat source events and raw payloads as in-process diagnostic details; do not
  forward them blindly across a process boundary.
