# Tell static host

`TellHostBuilder` assembles an immutable pre-boot module graph. Definitions
advertise interface capabilities, declare mandatory and optional dependencies,
and construct a fresh implementation through a factory. Factory arguments are
the declared dependencies in order; module code never receives a container.

The builder supports `with()`, `replace()`, `without()`, and `require()` before
`boot()`. A booted `TellHost` has named, typed accessors and no generic service
lookup or mutation methods. Replacement is therefore explicit and auditable,
and never claims live hot swapping.

Graph admission rejects duplicate module IDs, duplicate singleton providers,
non-interface advertisements, missing mandatory capabilities, and dependency
cycles. It aggregates independent graph errors before any factory runs.

Factories are invoked for every boot. Successfully constructed modules that
implement `CanDisposeTellModule` are disposed in reverse construction order.
Every cleanup is attempted after normal shutdown and partial boot failure; any
cleanup failures are reported only after the remaining modules were tried.

`TellHost::describe()` exposes the profile, module IDs, descriptions, and
capability edges. It never exposes factories, instances, resolved paths,
configuration payloads, or secrets.

## Standard runtime profile

`StandardTellProfile::runtime($directory)` composes `paths.standard`,
`secrets.standard`, `model.polyglot`, `clock.system`, `cancellation.memory`,
`agent.cognesy`, and `execution.default`. The SDK entry is the typed
`$host->runner()` capability.

Tests and applications can replace inference without subclassing Tell or
bypassing execution policy, tools, events, and persistence:

```php
$profile = StandardTellProfile::runtime(
    directory: $project,
    driverFactory: static fn () => FakeAgentDriver::fromResponses('done'),
);
$host = TellHostBuilder::fromProfile($profile)->boot();
$result = $host->runner()->run(
    TellRequest::prompt('Inspect this')->withDirectory($project),
);
```

For a custom model policy, replace `model.polyglot` with a definition that
provides `CanResolveTellModel`. Replacement is pre-boot and host-local. A model
is resolved once into the immutable agent definition; loop construction and
subagent re-entry reuse that definition and factory rather than opening a
second composition root.
