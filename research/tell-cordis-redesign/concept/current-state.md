# Current state

## Evidence base

The current Tell implementation has useful internal domains, but its assembly
is concrete and centralized:

- `packages/tell/src/Tell.php` constructs `TellAgentFactory` and `TellRuntime`.
- `packages/tell/src/TellApplication.php` constructs and registers every
  Symfony command.
- `packages/tell/src/Runtime/TellAgentFactory.php` resolves definitions,
  credentials, providers, model configuration, Agents capabilities and tools,
  policies, cancellation, sessions, traces, and workspaces.
- `packages/tell/src/Runtime/TellRuntime.php` selects execution modes and
  directly constructs arena stores, branch resolvers, and execution runners.
- public SDK handles such as `TellConversation`, `TellBranches`, and `TellRef`
  directly construct filesystem workspace services.

The command surface currently contains 19 Symfony command classes. Focused
source searches also find dozens of direct constructions of `ArenaStore`,
`BranchResolver`, `BranchConfigStore`, and `TellRuntime`. This is enough
duplication to justify a composition boundary, but not evidence that every
class needs an independently supervised lifecycle.

These are composition roots hidden inside product classes. A consumer can
replace an injected model driver in tests, but cannot replace workspace
storage, configuration, observation, command contributions, or execution
coordination as independent capabilities.

## What is already worth preserving

Tell is not a monolith without seams. The following boundaries are valuable
and should survive the redesign:

- `Canonical/*` defines provider-independent, content-addressed conversation
  records.
- `Workspace/*` protects append-only objects, compare-and-swap refs, branch
  lineage, and workspace validation.
- immutable request, result, event, branch, and inspection value objects form
  a coherent SDK vocabulary.
- `AgentLoop` and its hooks remain an Agents concern; Tell should compose them,
  not reimplement them.
- CLI rendering is already separate from canonical state.
- deterministic testing, cancellation, budgets, normalized events, direct tool
  dispatch, branch controls, and the one-run protocol provide acceptance
  behavior that can guard the migration.

The redesign moves construction and ownership. It does not rewrite those
domains merely to make the directory tree look different.

## Existing extension mechanism

`CapabilityDiscovery` in `cognesy/agents` reads Composer metadata and registers
zero-argument agent capability and tool factories. Tell invokes it while
building each agent loop.

Discovery already returns structured results and errors, but the current
`TellAgentFactory` discards that result. The redesign must surface discovery
failures instead of turning invalid extensions into silent absence.

That mechanism remains useful for agent-level extensions, but it is not a Tell
module kernel:

- it has no scoped cleanup;
- it cannot declare runtime service dependencies;
- it cannot restart consumers after a provider changes;
- it only constructs zero-argument capability and tool classes;
- it exposes no module health or lifecycle state; and
- it does not own CLI, workspace, configuration, or host composition.

The redesign must not overload this manifest with host modules. Agent
extension discovery becomes one adapter module behind a Tell contract.

## Current pressure points

### Factory concentration

`TellAgentFactory` changes for unrelated reasons. Adding a provider policy,
changing secret resolution, swapping a session store, attaching telemetry, or
altering tool discovery all modify the same concrete class.

### Runtime branching

`TellRuntime` duplicates synchronous and streaming branches for automatic,
stateless, durable, transient, and legacy-session paths. It also reaches into
workspace construction. Execution policy and storage policy are therefore
coupled.

The synchronous and streaming paths also repeat selection and discovery work.
A simplifying change should make synchronous execution drain the streaming
path where public result behavior is equivalent before module extraction.

### Process-global configuration

Current construction contains a `putenv()` placeholder and reads Tell path
inputs from process environment. This is unsafe in long-lived and concurrent
hosts. Path/environment ownership and driver configuration need explicit,
immutable inputs before a host abstraction is introduced.

### SDK and CLI drift risk

The CLI and PHP SDK construct overlapping concrete collaborators independently.
A new capability can appear in one surface without sharing the same host or
replacement rules with the other.

### Lifecycle ambiguity

Short-lived CLI execution masks resource ownership. Persistent workers, MCP
servers, long-lived framework processes, and persistent shell jobs need a clear
owner for listeners, transports, clients, subprocesses, and caches.

### Replacement is test-specific

`TellTestFactory` proves that an injected driver can work, but replacement is
not a general product contract. A first-class module model should make the same
operation available to applications without exposing internal factories.

## Existing building blocks and corrected prerequisites

`packages/utils` already contains tested `Context`, `Layer`, and `Key` types
for typed immutable composition. No product package currently uses them, and
right-biased layer merging could conceal duplicates. They warrant a focused
spike against ordinary named factories, not automatic adoption.

Agents now publishes abandonment from `AgentLoop::iterate()` through
`HookTrigger::OnAbandoned` and `AgentExecutionAbandoned`. This closes the major
streaming-safe-point prerequisite identified by the feedback: a future
supervisor can count a generator until exhaustion or abandonment without
inventing a separate execution lease.

Cordis PHP now has tagged releases and supports PHP `^8.2`, but it is not yet
resolvable as `cordis-php/cordis` from Packagist. It is therefore suitable for
a disposable path-repository spike, not a production Tell dependency today.

## Redesign constraint

The migration must preserve current consumer behavior while reversing the
dependency direction:

```text
current: SDK or CLI -> concrete factory -> every implementation
target:  SDK or CLI -> contracts <- externally selected modules
                           ^
                           |
                     static composition

later:   resource host -> optional Cordis lifecycle adapter
```

The old factories can remain temporary compatibility adapters. They are not
the target composition API.

## Current-to-target migration map

<!-- markdownlint-disable MD013 -->

| Current owner | Target capability or module | Migration treatment |
| --- | --- | --- |
| `Tell::open()` | standard `TellHost` composition | Keep as a compatibility facade over externally customizable wiring. |
| `TellApplication` | `cli.symfony` and `CanBuildTellApplication` | Inject a booted host and aggregate command contributors. |
| `TellAgentFactory` definition and loop assembly | `agent.cognesy` and `CanBuildTellAgent` | Move behind an adapter; keep Agents inference lifecycle separate from Cordis lifecycle. |
| `TellAgentFactory` provider and model selection | `model.polyglot` and `CanResolveTellModel` | Extract resolution and reasoning validation from agent construction. |
| `TellAgentFactory` credential lookup | `secrets.standard` and `CanResolveTellSecrets` | Publish a resolver in an isolated context, never resolved values. |
| `TellAgentFactory` tool discovery and dispatch | `extensions.composer`, `tools.standard` | Separate descriptive discovery from policy-bound invocation. |
| `TellAgentFactory` tracing and events | `observation.standard` and `CanObserveTellExecution` | Preserve the normalized event schema; make sinks replaceable consumers. |
| `TellRuntime` execution-mode routing | `execution.default` and `CanRunTell` | Keep stateless, transient, durable, and legacy choices as module policy. |
| `TellRuntime` workspace construction | `workspace.filesystem` | Consume workspace contracts instead of constructing stores and runners. |
| `TellConversation`, `TellBranches`, `TellRef`, and related facades | `CanAccessTellConversations`, `CanManageTellWorkspace` | Retain public vocabulary while replacing concrete construction with injected capabilities. |
| `ArenaStore`, branch stores, and canonical readers/writers | `workspace.filesystem` | Preserve them as cohesive internals guarded by one conformance boundary. |
| `TellConfig` and branch/request precedence logic | `configuration.standard` and `CanResolveTellConfiguration` | Resolve typed configuration with provenance outside storage and CLI rendering. |
| `TellTestFactory` | `testing.deterministic` | Generalize test-only injection into normal module replacement. |
| Symfony command classes and renderers | `cli.symfony` and `CanContributeTellCommands` | Keep formatting and exit mapping at the shell edge. |

<!-- markdownlint-enable MD013 -->
