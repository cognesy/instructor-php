# Developer experience

## Preserve the simple path

Existing code remains valid:

```php
$tell = Tell::open(__DIR__);
$result = $tell->run(TellRequest::prompt('Summarize the project.'));
```

`Tell::open()` uses the standard SDK profile. It is a convenience facade, not
a process-global singleton.

## Make programmatic control obvious

Applications that need ownership or replacement use a pre-boot builder:

```php
$host = TellHost::standard()
    ->replace(
        'model.polyglot',
        fn () => new ApplicationModelModule($gateway),
    )
    ->replace(
        'observation.standard',
        fn () => new PsrTelemetryModule($logger),
    )
    ->boot();

try {
    $tell = $host->open(__DIR__);
    $result = $tell->run(TellRequest::prompt('Review the change.'));
} finally {
    $host->dispose();
}
```

`replace()` and `without()` are unavailable after boot. The ordinary SDK has
no runtime reconciliation API.

## First-class testing

The convenience API remains:

```php
$tell = Tell::testing($directory, 'first response', 'second response');
```

The controllable equivalent replaces only the intended seams:

```php
$host = TellHost::testing()
    ->withWorkspace(fn () => new InMemoryWorkspaceModule())
    ->withResponses('first response', 'second response')
    ->boot();
```

A scripted driver does not silently bypass policy, tools, normalized events,
or canonical publication.

## Framework ownership

Framework adapters receive or construct `TellHost`; they do not create
`TellAgentFactory` internally. Laravel and Symfony can own one host per
application or worker scope. Queue workers dispose and rebuild at a job
boundary. Shell jobs use the separate opt-in resource host; MCP remains a
future resource candidate. Neither implies live reconciliation.

## Discoverability

Before boot, `TellHostBuilder::describe()` reports selected modules,
requirements, providers, and graph errors. After boot, the static host reports
active or disposed state and redacted construction failures.

The shell-job resource host reports pending, active, failed, unloading, and
disposed module states where they are real. Restart revision, in-flight
inference count, and reconciliation result are absent because the current
no-go decision introduces no supervisor.

Diagnostics never expose raw service instances or secret-bearing settings.

## Failure messages

Composition errors identify module, capability, and remedy, and aggregate all
missing requirements in one pass:

```text
Module "agent.cognesy" requires capability
"Cognesy\Tell\Contract\CanResolveTellModel", but no selected module provides it.
Add a model module before TellHost::boot().
```

## CLI, protocol, and SDK parity

Commands and one-run protocol workers call the same capabilities as PHP
facades. `TellApplication` no longer constructs alternate workspace readers,
provider catalogues, or runtimes. The shell edge owns input translation,
rendering, frames, and exit status.

Every user-visible capability has one domain contract, a developer-friendly
PHP facade, and optional CLI or protocol adapters. CLI availability is not the
definition of the capability.
