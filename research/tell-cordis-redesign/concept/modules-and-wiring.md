# Modules and wiring

## Standard module set

The first implementation uses a small set of vertical, static modules. A
module may publish related contracts when they share state and dependencies.

### `paths.standard`

Provides `CanResolveTellPaths`. It owns user-level defaults, workspace-relative
locations, and reads of Tell-specific environment inputs. No other module
mutates or scans process environment.

### `execution.default`

Provides `CanRunTell`. It owns current automatic, stateless, durable,
transient, and any temporarily supported legacy routing. It consumes agent,
workspace, configuration, observation, clock, cancellation, and tools.

### `agent.cognesy`

Provides `CanBuildTellAgent`. It adapts definition loading,
`DefinitionLoopFactory`, policy, budgets, subagents, cancellation, and the
explicit deterministic-driver seam.

### `model.polyglot`

Provides `CanResolveTellModel`. It consumes secrets and owns presets, DSNs,
model overrides, provider capabilities, and reasoning options.

### `secrets.standard`

Provides `CanResolveTellSecrets`. It composes environment, workspace `.env`,
and Tell credential sources without publishing a secret map.

### `workspace.filesystem`

Provides workspace management, conversation access, and branch-configuration
reading. It owns `.tell`, canonical objects, refs, branches, locks, inspection,
and compaction persistence. It does not own aggregate configuration precedence.

### `configuration.standard`

Provides `CanResolveTellConfiguration`. It merges request, optional branch,
host, and default settings with explicit provenance. User-level default files
are configuration inputs resolved through `paths.standard`.

### `extensions.composer`

Provides `CanCatalogueTellExtensions`. It adapts Agents Composer metadata and
Tell catalogues, preserving structured discovery failures rather than
discarding them.

### `tools.standard`

Provides `CanDispatchTellTool` and ordered agent-tool contributions. It owns
argument validation, policy effects, approval, output bounds, cancellation,
coding tools, ask-user, and subagent dispatch adapters.

### `observation.standard`

Provides `CanObserveTellExecution`. It owns the normalized event envelope and
trace adapter. Renderers and telemetry consume that envelope rather than
inventing alternate event schemas.

### `cli.symfony`

Provides `CanBuildTellApplication` and aggregates command contributions. It
owns every current command, custom routing and plane maps, input translation,
rendering, diagnostics, and exit mapping.

### `protocol.one-run`

Provides `CanRunTellProtocol`. It owns the bounded external JSONL protocol,
including schema validation and exactly one terminal outcome.

### `testing.deterministic`

Contributes a scripted driver at the defined agent/model seam. It keeps real
policy, tools, events, execution, and persistence unless the test explicitly
replaces those modules too.

### `sessions.legacy`

Exists only if the baseline decision retains legacy sessions for a bounded
period. No new module depends on the concrete legacy format.

## Factory-backed composition

Concrete modules meet only in a standard or application composition root:

```php
$host = TellHost::builder()
    ->with('paths.standard', fn () => new StandardPathsModule($environment))
    ->with('secrets.standard', fn ($d) => new StandardSecretsModule(
        $d->require(CanResolveTellPaths::class),
    ))
    ->with('model.polyglot', fn ($d) => new PolyglotModelModule(
        $d->require(CanResolveTellSecrets::class),
    ))
    ->with('workspace.filesystem', fn ($d) => new FilesystemWorkspaceModule(
        $d->require(CanResolveTellPaths::class),
    ))
    ->withStandardRuntime()
    ->boot();
```

This is ordinary PHP and reviewable by static tools. A standard profile returns
the same unbooted builder so applications can replace definitions explicitly.

## Replacement

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
```

Replacement addresses a module ID and is available only before boot in the
static host. Admission checks the resulting graph and advertised interfaces.
Replacing one part of a multi-capability module requires a composition that
makes new ownership explicit.

## Source and dependency rules

Modules import contracts, never sibling implementations. Architecture tests
enforce this before any Composer package split.

`packages/agent-ctrl` is a vocabulary and behavior reference for controlled
agent processes, not an automatic dependency. The D11 Harness remains the
public DX acceptance surface.

## Standard profiles

- SDK: standard runtime without CLI assembly;
- CLI: SDK plus Symfony commands and one-run protocol;
- testing: SDK with explicit deterministic driver and optional in-memory
  workspace;
- minimal stateless: execution, agent, model, secrets, paths, observation, and
  tools; and
- resource host: a later opt-in profile adding the Cordis supervisor and one
  accepted scoped-resource feature.

Profiles are factory sets. They do not weaken graph validation.
