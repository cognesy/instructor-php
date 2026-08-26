# Capability catalogue

## Contract granularity

A capability represents behavior an application may replace as a unit. It
does not mirror every current class. Cohesive operations sharing one state
model stay together, especially canonical workspace operations.

Signatures below are directional. The extraction step owns exact value types
and may depend on public Agents and Polyglot values already exposed by Tell.

## Essential runtime contracts

### `CanRunTell`

Owns synchronous and streaming execution of `TellRequest`. The default module
coordinates execution mode, effective configuration, workspace access, agent
construction, persistence, and result assembly.

Current source: `TellRuntime`.

### `CanBuildTellAgent`

Resolves an agent definition and builds a configured `AgentLoop`. It delegates
model resolution, extension catalogue, observation, clock, and cancellation.

Current source: the loop-building slice of `TellAgentFactory`.

### `CanResolveTellModel`

Resolves provider-independent request intent into validated model
configuration or a driver. It owns reasoning support and explicit model
overrides, but not secret storage.

Current source: `TellAgentFactory::llmConfig()`, `TellProviderCatalogue`, and
`TellReasoningSupport`.

### `CanResolveTellSecrets`

Resolves values from approved sources without exposing source storage to the
caller or resolved values in diagnostics.

Current source: `TellCredentialStore` and
`TellAgentFactory::secretResolver()`.

## Workspace and configuration contracts

### `CanManageTellWorkspace`

Initializes, discovers, and validates a Tell workspace using boundary values
rather than filesystem implementation classes.

### `CanAccessTellConversations`

Opens a cohesive gateway for canonical records, refs, branches, history,
inspection, compare-and-swap publication, clear, reset, and compaction.

The filesystem implementation keeps storage and mutation invariants together.

### `CanReadTellBranchConfiguration`

Reads branch-local, secret-free execution settings. The filesystem workspace
provides this internal dependency; it does not provide the aggregate
configuration capability.

### `CanResolveTellConfiguration`

Produces immutable effective settings using request intent, branch settings,
host settings, and defaults. It consumes an optional branch reader and reports
configuration provenance.

### `CanResolveTellPaths`

Returns explicit user, workspace, cache, definition, trace, and credential
locations. It is the sole reader of Tell-specific environment inputs. Product
modules do not call `getenv()` or mutate process environment.

## Extension and operation contracts

### `CanCatalogueTellExtensions`

Returns agents, providers, models, Agents capabilities, and tools as typed
descriptors. Discovery returns both accepted descriptors and structured
errors; callers must not silently discard invalid manifests.

### `CanDispatchTellTool`

Validates and invokes a named tool under policy, cancellation, approval, and
output bounds. It remains separate from agent construction so applications can
make controlled direct calls.

### `CanObserveTellExecution`

Accepts the normalized Tell event envelope with stable ordering and redaction.
Current `TellEvent::source` objects may remain on compatibility facades, but
raw Agents or provider events are not the module contract.

Sinks may adapt the normalized stream to traces, PSR logging, OpenTelemetry,
or application events.

### `CanContributeTellCommands`

Contributes validated command descriptors or Symfony commands to CLI
assembly. The Step 3 spike decides the exact boundary. Contributors do not
construct the application or register globally.

### `CanBuildTellApplication`

Builds the shell edge from command contributions and output adapters. It is
required only by the CLI profile.

### `CanRunTellProtocol`

Runs the bounded one-run worker protocol and maps typed requests, events,
terminal frames, and exit status. Protocol framing stays separate from
execution and rendering.

## Existing contracts to reuse

Reuse `CanProvideCancellationSignal` from Agents and `CanReadTellClock`.
Deterministic testing supplies an explicit driver seam inside agent/model
composition rather than replacing every agent responsibility.

## Compatibility-only capability

Legacy sessions may be represented by `CanAccessLegacyTellSessions` only if
Step 1 assigns a supported retirement horizon. Do not create a permanent module
for behavior that should instead be removed.

## Cardinality rules

- execution, workspace, conversations, model, secrets, configuration, paths,
  observation, application, protocol, and direct dispatch are singletons;
- branch configuration is an optional singleton dependency of configuration;
- command, tool, and extension contributions use explicit ordered
  aggregators; and
- cancellation and clock are host-provided singleton values.

Duplicate singleton providers fail graph admission. Replacement is explicit
and auditable.
