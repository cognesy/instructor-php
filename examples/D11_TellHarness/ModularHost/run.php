---
title: 'Tell Harness: Embed and Replace Host Capabilities'
docname: 'tell_harness_modular_host'
order: 13
id: 'd1113'
tags:
  - 'tell'
  - 'tell-harness'
  - 'testing'
---
## Overview

Boot Tell as an explicit application host, replace one capability before boot,
run through the stable SDK contract, inspect the admitted graph, and dispose the
host deterministically. This is the programmable path for applications that
need more control than `Tell::open()`.

## Example

```php
<?php
require 'examples/boot.php';
require_once dirname(__DIR__).'/Support.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\Composition\TellModuleDefinition;
use Cognesy\Tell\Contracts\CanObserveTellExecution;
use Cognesy\Tell\Contracts\Data\TellEventEnvelope;
use Cognesy\Tell\Runtime\TellPaths;
use Cognesy\Tell\Composition\TellHost;
use Cognesy\Tell\TellRequest;

$project = TellHarnessExample::project();
$events = new ArrayObject;
$observer = new class($events) implements CanObserveTellExecution {
    public function __construct(private ArrayObject $events) {}

    public function observe(TellEventEnvelope $event): void
    {
        $this->events->append($event->toArray());
    }
};
$observation = new TellModuleDefinition(
    id: 'observation.example',
    provides: [CanObserveTellExecution::class],
    factory: static fn (): object => $observer,
);
$paths = new TellPaths(
    packageAgents: dirname(__DIR__, 3).'/packages/tell/resources/agents',
    home: $project.'/.tell-host-example',
);
$host = TellHost::standard(
    directory: $project,
    paths: $paths,
    driverFactory: static fn () => FakeAgentDriver::fromResponses('host-controlled answer'),
)->replace('observation.standard', $observation)->boot();

try {
    $result = $host->runner()->run(TellRequest::prompt('Run through the host.')->withDirectory($project));
    $description = $host->describe();

    echo trim($result->text())."\n";
    echo 'Modules: '.count($description->modules)."\n";
    echo 'Observed events: '.count($events)."\n";

    assert(trim($result->text()) === 'host-controlled answer');
    assert(count($events) > 0);
} finally {
    $host->dispose();
    TellHarnessExample::remove($project);
}
```

## Key Points

- `TellHost::standard()` returns an immutable pre-boot builder with `with()`,
  `replace()`, and `without()` operations.
- Replacement is static: the graph validates once during `boot()` and cannot
  be mutated while a run is active.
- Host descriptions expose module identities and contracts, never secrets or
  model output.
- The application that boots a host owns `dispose()`; `finally` is the normal
  lifecycle boundary.
