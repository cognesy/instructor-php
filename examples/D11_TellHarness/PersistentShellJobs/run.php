---
title: 'Tell Harness: Own Persistent Shell Jobs'
docname: 'tell_harness_persistent_shell_jobs'
order: 14
id: 'd1114'
tags:
  - 'tell'
  - 'tell-harness'
  - 'shell-jobs'
  - 'lifecycle'
---
## Overview

Boot the opt-in Tell resource host, approve a bounded shell job, read its output
after `start()` returns, and release every process with one explicit owner. The
ordinary Tell SDK and CLI do not boot this Cordis-backed host.

## Example

```php
<?php
require 'examples/boot.php';
require_once dirname(__DIR__).'/Support.php';

use Cognesy\Tell\Resource\TellResourceObservers;
use Cognesy\Tell\Resource\TellShellJobApprovals;
use Cognesy\Tell\TellResourceEvent;
use Cognesy\Tell\TellResourceHost;
use Cognesy\Tell\TellShellJobPolicy;
use Cognesy\Tell\TellShellJobRequest;

$project = TellHarnessExample::project();
$events = new ArrayObject;
$host = TellResourceHost::shellJobs(
    project: $project,
    policy: new TellShellJobPolicy(
        maxConcurrentJobs: 2,
        maxLifetimeMs: 5_000,
        maxRetainedOutputBytes: 16_384,
    ),
    approval: TellShellJobApprovals::allowAll(),
    observer: TellResourceObservers::callback(
        static fn (TellResourceEvent $event) => $events->append($event->toArray()),
    ),
)->boot();

try {
    $script = <<<'PHP'
fwrite(STDOUT, "started\n");
usleep(50_000);
fwrite(STDOUT, "finished\n");
fwrite(STDERR, "diagnostic\n");
PHP;
    $command = escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($script);
    $started = $host->jobs()->start(
        TellShellJobRequest::command($command)
            ->forMilliseconds(2_000)
            ->retaining(4_096)
            ->named('example-worker'),
    );

    // The process remains host-owned after start() returns. Callers retain
    // only immutable snapshots and cursored output, never a raw process handle.
    $finished = $host->jobs()->wait($started->id, 3_000);
    $output = $host->jobs()->read($started->id);

    echo $output->text();
    echo 'State: '.$finished->state->value."\n";
    echo 'Resource events: '.count($events)."\n";

    assert($finished->isTerminal());
    assert($finished->exitCode === 0);
    assert(str_contains($output->text(), 'started'));
    assert(str_contains($output->text(), 'diagnostic'));
} finally {
    // This also cancels any still-running jobs, in reverse ownership order.
    $host->dispose();
    TellHarnessExample::remove($project);
}
```

## Key Points

- `TellResourceHost::shellJobs(...)->boot()` is deliberately separate from
  `Tell::open()` and `TellHost::standard()`.
- Denial is the default. An embedding application must supply an approval
  policy before the host creates a job identity or starts a process.
- The host enforces project-directory containment, concurrency, lifetime,
  retained-output, read, and cancellation-grace bounds.
- Snapshots and cursored output are immutable. Raw Cordis contexts, fibers,
  process handles, and pipes do not escape the owner scope.
- Resource events use `tell.resource.event.v1`; they contain hashes, counts,
  states, and error classes, never commands or output.
- Always call `dispose()` in `finally`; disposal is idempotent and cancels work
  that has not reached a terminal state.
