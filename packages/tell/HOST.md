# Tell standalone composition host

The framework-neutral SDK is composed by
`Cognesy\Tell\Composition\Standalone\Profile\StandaloneTellHost`. Default
provider selection is isolated in `Composition\Standalone\Profile`; reusable
graph and lifecycle machinery is isolated in `Composition\Standalone\Host`.
Runtime, workspace, tool, and configuration services depend only on focused
contracts; they never receive the host, its provider registry, a framework
container, or PSR-11.

`TellHostBuilder` assembles an immutable pre-boot module graph. Definitions
advertise interface capabilities, declare mandatory and optional dependencies,
and construct a fresh implementation through a factory. Factory arguments are
the declared dependencies in order; module code never receives a container.

The builder supports `with()`, `replace()`, `without()`, and `require()` before
`boot()`. A booted `TellHost` has named, typed accessors and no generic service
lookup or mutation methods. Replacement is explicit, pre-boot, and host-local;
Tell does not claim live hot swapping.

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

## Standard profiles

`StandardTellProfile::runtime($directory)` is the headless SDK and worker
profile. It composes paths, secrets, model selection, clock, cancellation,
tracing, agent construction, workspace and conversation access, configuration,
extension and Polyglot provider discovery, tools, observation, runtime creation,
execution, and the one-run protocol. It does not construct command or Symfony
Console services.

`StandardTellProfile::cli($directory)` extends that headless profile with the
core command contribution and `application.symfony-console` modules. The
`Adapter\Console` namespace is a CLI adapter based on the Symfony Console
component; it is not a Symfony Framework dependency-injection integration.

`StandaloneTellHost::open()` is the standalone SDK entry point. It boots the
runtime profile and constructs `Tell` from explicit runner, workspace,
conversation, provider-catalogue, tool, and disposal capabilities. A
long-running worker should boot one profile and reuse its factories for many
isolated executions, then dispose the host when the worker stops.

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

## Future framework hosts

Future Symfony and Laravel integration packages can map their native container
bindings to the contracts under `Cognesy\Tell\Core\Contract` and construct `Tell`
directly. They should own their framework
lifecycle and configuration adapters. Tell core will not expose either
container, adopt PSR-11 as a service locator, or embed one framework container
inside another.

`tests/Integration/HostCompositionConformanceTest.php` exercises lifecycle,
provider replacement, and execution isolation independently of the standalone
graph implementation so future hosts can run the same contract.
