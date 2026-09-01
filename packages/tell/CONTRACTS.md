# Tell boundary and capability census

This document records the P2 hard-cut baseline. It classifies every interface
currently owned by Tell, names the demonstrated provider and consumer for each
replacement seam, and records the intended destination in the target
architecture. It is the completed hard-cut ledger for the five-category source
layout.

The target dependency direction is:

```text
Data <- Core/Contract <- Core <- Capability <- Composition
                         ^                    ^
                         +------ Adapter -----+
```

## Decision vocabulary

- **Core contract**: stable behavior required by the headless runtime.
- **Capability contract**: behavior whose implementation is selected by a host.
- **Adapter port**: a boundary owned by Console or Protocol delivery.
- **Provider-internal port**: a seam required inside one capability family, not
  a top-level host capability.
- **Domain algebra**: an interface defining valid domain values rather than a
  replaceable service.
- **Composition-internal port**: host graph ownership or lifecycle machinery.
- **Delete**: no demonstrated provider and consumer; do not preserve it for a
  hypothetical extension.

<!-- markdownlint-disable MD013 -->

## Interface census

| Current interface | Classification | Demonstrated role | P2 destination |
| --- | --- | --- | --- |
| `Core/Contract/Execution/CanRunTell` | Core contract | `DefaultTellRunner`; consumed by `Tell`, protocol, and conversation facades | complete |
| `Core/Contract/Execution/CanCreateTellRuntime` | Core factory contract | composition-owned `StandardTellRuntimeFactory`; consumed by runner, protocol, commands, and tool dispatch | complete |
| `Core/Contract/Execution/CanExecuteTellRuntime` | Core execution contract | `Core/Execution/TellRuntime`; created per run for delivery and direct-tool adapters | complete |
| `Core/Contract/Agent/CanBuildTellAgent` | Capability contract | composition-built `TellAgentFactory`; consumed by runtime, tools, commands, and conversations | complete |
| `Core/Contract/Agent/CanLoadTellAgentDefinitions` | Capability contract | filesystem definition loader; consumed by agent construction | complete |
| `Core/Contract/Agent/CanContributeTellAgent` | Ordered contribution | Composer discovery, coding, ask-user, subagent, and standard capability providers; consumed by agent construction | complete |
| `Core/Contract/Model/CanResolveTellModel` | Capability contract | `PolyglotTellModelResolver`; consumed by agent construction | complete |
| `Core/Contract/Secrets/CanResolveTellSecrets` | Capability contract | `StandardTellSecretResolver`; consumed by model resolution | complete |
| `Core/Contract/Secrets/CanManageTellCredentials` | Capability contract | filesystem credential store; consumed only by the console auth adapter | complete |
| `Core/Contract/Configuration/CanResolveTellConfiguration` | Capability contract | `StandardTellConfigurationResolver`; consumed by runtime construction | complete |
| `Core/Contract/Workspace/CanReadTellBranchConfiguration` | Capability contract | filesystem and memory workspace providers; optional input to configuration resolution | complete |
| `Core/Contract/Paths/CanResolveTellPaths` | Capability contract | `StandardTellPathResolver`; consumed throughout host construction | complete |
| `Core/Contract/Workspace/CanManageTellWorkspace` | Capability contract | filesystem and memory providers; consumed by `Tell` and `TellWorkspace` | complete |
| `Core/Contract/Workspace/CanOpenTellWorkspace` | Capability contract | filesystem and memory providers; consumed by runtime, commands, and facades | complete |
| `Core/Contract/Workspace/CanProvideTellWorkspace` | Standard-profile provider contract | complete lifecycle, open, and branch-configuration surface; implemented by filesystem and memory providers | complete |
| `Core/Contract/Workspace/CanOpenTellExecutionWorkspace` | Capability contract | backend-neutral execution-workspace provider; consumed by runtime construction | complete |
| `Core/Contract/Workspace/CanUseTellExecutionWorkspace` | Core workspace contract | backend-neutral execution handle; consumed by `Core/Execution/TellRuntime` | complete |
| `Core/Contract/Workspace/CanUseTellWorkspaceArena` | Core workspace contract | filesystem and memory arenas; consumed by workspace policy | complete |
| `Core/Contract/Workspace/CanUseTellBranchSelectionStore` | Provider-internal port | filesystem and memory stores; consumed through opened workspace context | complete |
| `Core/Contract/Workspace/CanUseTellBranchConfigurationStore` | Provider-internal port | filesystem and memory stores; consumed through opened workspace context | complete |
| `Core/Contract/Workspace/CanAccessTellConversations` | Core contract | backend-neutral conversation provider; consumed by `Tell` and `TellWorkspace` | complete |
| `Core/Contract/Workspace/CanInspectTellConversation` | Core contract | read-only history, transcript, and context projection shared by conversation handles | complete |
| `Core/Contract/Workspace/CanMaintainTellConversation` | Core contract | clear and compact operations shared by conversation handles | complete |
| `Core/Contract/Workspace/CanUseTellConversation` | Core contract | backend-neutral conversation facade; returned by conversation access | complete |
| `Core/Contract/Workspace/CanManageTellBranches` | Core contract | backend-neutral branch facade; returned by conversation access | complete |
| `Core/Contract/Workspace/CanManageTellSessions` | Core contract | backend-neutral session catalogue and removal facade; returned by conversation access | complete |
| `Core/Contract/Workspace/CanUseTellBranch` | Core contract | backend-neutral branch handle; returned by conversation access | complete |
| `Core/Contract/Workspace/CanUseTellRef` | Core contract | backend-neutral immutable-ref handle; returned by conversation access | complete |
| `Core/Contract/Workspace/CanConfigureTellBranch` | Core contract | backend-neutral branch configuration facade; returned by conversation access | complete |
| `Core/Contract/Discovery/CanCatalogueTellExtensions` | Capability contract | Composer catalogue; exposed by the host discovery API | complete |
| `Core/Contract/Discovery/CanCatalogueTellProviders` | Capability contract | Polyglot catalogue; consumed by discovery, branch configuration, and console adapters | complete |
| `Core/Contract/Tool/CanDispatchTellTool` | Capability contract | `StandardTellToolDispatcher`; consumed by CLI tool execution | complete |
| `Core/Contract/Observation/CanObserveTellExecution` | Capability contract | null and PSR strategies; consumed by runtime execution | complete |
| `Core/Contract/Observation/CanTraceTellExecution` | Capability contract | filesystem trace strategy; consumed by agent/runtime/conversation paths | complete |
| `Adapter/Console/Symfony/Contract/CanContributeTellCommands` | Adapter port | `CoreTellCommandContributor`; consumed by CLI application assembly | complete |
| `Adapter/Console/Symfony/Contract/CanBuildTellConsoleApplication` | Adapter port | Symfony builder; consumed by CLI bootstrap | complete |
| `Adapter/Console/Symfony/Contract/CanRunTellConsoleApplication` | Adapter port | Symfony runner; returned by the console builder | complete |
| `Adapter/Protocol/OneRun/Contract/CanRunTellProtocol` | Adapter port | one-run protocol; consumed by protocol command/bootstrap | complete |
| `Adapter/Protocol/OneRun/Contract/CanWriteTellProtocolFrames` | Adapter port | protocol writer; consumed by one-run protocol | complete |
| `Core/Contract/Execution/CanDisposeTellResources` | Core lifecycle contract | host implementation; consumed by the public `Tell` facade | complete |
| `Core/Contract/Execution/CanObserveTellRun` | Core execution contract | `TellRun`; returned by runner and public `Tell` facade | complete |
| `Core/Contract/Agent/CanDescribeTellDelegation` | Provider-internal port | `TellDelegationScope`; passed through agent construction | complete |
| `Core/Contract/Agent/CanRecordTellAgentDiagnostics` | Provider-internal port | `TellDiagnostics`; records agent assembly diagnostics | complete |
| `Core/Contract/ShellJob/CanManageTellShellJobs` | Capability contract | shell job service; exposed by the shell job host | complete |
| `Core/Contract/ShellJob/CanApproveTellShellJobs` | Capability contract | standard approvals; consumed by shell job execution | complete |
| `Core/Contract/ShellJob/CanObserveTellShellJobs` | Capability contract | null and aggregate observers; consumed by shell job events | complete |
| `Core/Contract/Execution/CanReadTellClock` | Provider-internal port | system clock; consumed by execution-budget logic | complete |
| `Core/Workspace/Arena/Record/StoredRecord` | Domain algebra | conversation and turn records; consumed by arena codecs/stores | complete |
| `Adapter/Console/Render/OutputRenderer` | Adapter port | events, human, JSON, text, and TOON renderers; consumed by Console | complete |
| `Adapter/Console/Operational/CanDescribeOperationalPlane` | Adapter-internal port | implemented by commands; consumed by `PlaneMap` | complete |
| `Composition/Standalone/Host/CanDisposeTellModule` | Composition-internal port | disposable host modules; consumed by reverse-order cleanup | complete |

`CanProvideCancellationSignal` is owned by Agents, not Tell. Tell reuses it as
a singleton Core execution dependency instead of defining a parallel contract.

## Host capability review

The following two tables jointly contain every field from the P2 capability
review template. “None” means no supported second production strategy exists;
test doubles are evidence of replaceability but are not advertised strategies.

| Capability | Behavior that varies | Stable contract | Boundary data | Default strategy | Other strategies | Cardinality | Lifecycle scope |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Execution | run and stream orchestration | `CanRunTell` | request, progress, result | `DefaultTellRunner` | none | singleton | host; creates runs |
| Runtime factory | per-run runtime construction | `CanCreateTellRuntime` | cancellation, runtime handle | `StandardTellRuntimeFactory` | test factory | singleton | host factory; per run output |
| Agent | definition lookup and loop construction | `CanBuildTellAgent` | request, definition, diagnostics | composition-built `TellAgentFactory` | injected builders | singleton | host |
| Agent contributions | ordered agent/tool assembly | `CanContributeTellAgent` | request-local assembly | six explicit standard-profile contributions | replacement or omitted contributions | ordered contribution | host selection, request application |
| Model | request-to-model resolution | `CanResolveTellModel` | request, `LLMConfig` | `PolyglotTellModelResolver` | injected resolvers | singleton | host |
| Secrets | redaction-aware secret lookup | `CanResolveTellSecrets` | resolved secret values | `StandardTellSecretResolver` | injected resolvers | singleton | host |
| Credential management | secret-free status and explicit set/remove | `CanManageTellCredentials` | credential status | filesystem credential store | injected managers | singleton | CLI host |
| Configuration | precedence and effective policy | `CanResolveTellConfiguration` | request, effective configuration | `StandardTellConfigurationResolver` | injected resolvers | singleton | host |
| Branch configuration | optional branch overrides | `CanReadTellBranchConfiguration` | branch configuration | filesystem workspace provider | memory workspace provider | optional singleton | host |
| Paths | directory policy | `CanResolveTellPaths` | resolved paths | `StandardTellPathResolver` | injected resolvers | singleton | host |
| Workspace | lifecycle and discovery | `CanManageTellWorkspace` | workspace information | filesystem provider | memory provider | singleton | host |
| Conversations | canonical conversation access | `CanAccessTellConversations` | conversation and branch values | backend-neutral Core provider | filesystem or memory workspace backend | singleton | host |
| Discovery | Composer extension inspection | `CanCatalogueTellExtensions` | extension catalogue | Composer catalogue | none | singleton | host |
| Provider catalogue | Polyglot provider and model inspection | `CanCatalogueTellProviders` | provider, connection, and model catalogue | Polyglot catalogue | injected catalogues | singleton | host |
| Tool dispatch | policy-bound direct tool calls | `CanDispatchTellTool` | tool request and result | standard dispatcher | injected dispatchers | singleton | host |
| Observation | normalized execution events | `CanObserveTellExecution` | event envelope | null observer | PSR observer | singleton | host |
| Tracing | persistent execution traces | `CanTraceTellExecution` | request and loop events | filesystem tracer | injected tracers | singleton | host |
| Commands | CLI command contribution | `CanContributeTellCommands` | command descriptors | core contributor | host contributions | ordered contribution | host |
| Console application | shell assembly and execution | builder and runner ports | command descriptors, arguments, exit code | Symfony builder/runner | future framework adapter | singleton | worker/profile |
| Protocol | bounded one-run framing | protocol and frame-writer ports | protocol request, progress, result | one-run protocol/writer | future transport adapters | singleton plus per-run writer | worker and run |
| Cancellation | cooperative stop signaling | Agents cancellation contract | cancellation signal | in-memory source | injected source | singleton | worker/profile |
| Clock | monotonic execution time | `CanReadTellClock` | integer milliseconds | system clock | test clocks | singleton | host |

| Capability | Required capabilities | Optional capabilities | Provider consumers | Conformance laws | Why this is not Core | Why this exists now |
| --- | --- | --- | --- | --- | --- | --- |
| Execution | runtime factory | none | `Tell`, conversations, protocol | run/stream terminal result equivalence | execution mode policy is selectable | public SDK already runs all modes |
| Runtime factory | agent, paths, cancellation, configuration, observation, tracing | none | runner, protocol, tools, commands | fresh per-run state; injected services retained | construction policy varies by host | all public execution enters here |
| Agent | paths, model, clock, tracing, definitions, ordered contributions | none | runtime, tools, commands, conversations | definition identity and readiness are stable | model/driver/tool assembly varies | runtime needs an agent loop today |
| Model | paths, secrets | none | agent builder | deterministic precedence and no eager I/O | provider/model selection varies | requests select configured models |
| Secrets | paths | none | model resolver | precedence, redaction, no secret leakage | host environments supply secrets differently | model credentials are required today |
| Credential management | paths | none | auth adapter | values never cross the boundary | storage and management strategy varies | CLI auth needs explicit mutation today |
| Configuration | paths | branch configuration | runtime factory | explicit precedence and immutable result | configuration sources vary by host | every run resolves policy today |
| Branch configuration | workspace storage | none | configuration resolver | absent is valid; secret-free values | not every host has branches | filesystem workspaces expose overrides today |
| Paths | none | none | most standard modules | deterministic resolution; no hidden host boot | installed/custom paths vary | all persistence boundaries need paths |
| Workspace | none | none | `Tell`, workspace facade | filesystem and memory lifecycle conformance | persistence strategy varies | durable and isolated tests both exist |
| Conversations | agent, execution, tracing, paths, provider catalogue | none | `Tell`, workspace facade | branch/ref semantics independent of backend | storage strategy must vary | SDK exposes conversations today |
| Discovery | none | none | host discovery API | passive import; deterministic diagnostics | package metadata source varies | public host inspection exists today |
| Tool dispatch | agent, runtime, cancellation | none | CLI tool command | policy, cancellation, normalized result | tool graph and execution host vary | direct tool execution is public CLI behavior |
| Observation | none | none | runtime | normalized redacted order; null is lossless to runtime | sinks are optional and replaceable | callers use null and PSR sinks today |
| Tracing | paths | none | agent, runtime, commands, conversations | trace shape/redaction; execution unaffected by sink | persistence is optional and replaceable | filesystem traces are a current feature |
| Commands | agent, runtime, tools, tracing, paths, cancellation, protocol, provider catalogue | none | Console application builder | stable names; duplicate semantic keys rejected | command sets are delivery concerns | the CLI assembles commands today |
| Console application | command contributions | none | CLI bootstrap | descriptors stay framework-neutral; exit code preserved | Symfony is an adapter choice | the shipped binary uses Symfony today |
| Protocol | runtime factory | cancellation | protocol command and worker | one request; one terminal frame; bounded frames | framing/transport is delivery behavior | agent protocol workers ship today |
| Cancellation | none | none | agent, runtime, tools, protocol | cancellation is cooperative and host-scoped | signal source varies by host | controlled runs use cancellation today |
| Clock | none | none | agent execution budget | monotonic milliseconds | time source must be deterministic in tests | execution budgets use it today |

## Auxiliary capability review

| Capability | Contract and data | Providers | Consumers and laws | Decision |
| --- | --- | --- | --- | --- |
| Shell jobs | manage, approve, and observe contracts; shell request/event/snapshot values | process job service, approvals, null/aggregate observers | opt-in `StandardTellShellJobProfile`; approval before start, ordered output, cancellation, disposal | retain contracts in Core, strategies in Capability, and host ownership/default selection in Composition |
| Arena storage | `CanUseArena` and immutable stored records | filesystem and memory arenas | branch stores/history; both pass the same CAS and record conformance suite | retain as workspace-provider internal until the workspace split exposes the final contract |
| Output rendering | `OutputRenderer` plus execution/result values | human, JSON, text, TOON, events | Console command; output selection must not affect execution | retain as Console adapter strategy, not a Core host capability |
| Operational metadata | `CanDescribeOperationalPlane` and plane values | Console commands | `PlaneMap`; descriptions are read-only and deterministic | retain inside Console adapter |
| Resource disposal | public resource and internal module disposal ports | host and owned modules | `Tell` and reverse-order cleanup | retain; host ownership is invariant while module disposal is composition-internal |
| Stored record algebra | `StoredRecord` | conversation root and turn values | codecs and arena stores; kind/schema/hash stability | retain as domain algebra, not a provider capability |

<!-- markdownlint-enable MD013 -->

## Hard-cut decisions from the census

- `CanContributeTellExtensions` was deleted. It had no provider and no consumer;
  Composer discovery already has the concrete `CanCatalogueTellExtensions`
  boundary.
- `CanContributeTellTools` was deleted. It had no provider and no consumer;
  current tool assembly enters through agent construction and
  `CanDispatchTellTool`.
- Their host accessors and cardinality entries were deleted with them. No alias,
  deprecation, forwarding interface, or compatibility bridge remains.

## Completed P2 dependency repairs

Data imports no other Tell namespace, Core contracts expose only Data or Core
types, and Core contains no selected providers. Observation, execution,
workspace, agent, tool, discovery, configuration, model, paths, secrets, and
shell-job implementations are isolated by capability family and strategy.
Console and protocol delivery code is under Adapter. The standalone Profile is
the sole selector of standard providers; Host owns only graph and lifecycle
mechanics.

`TellCapabilityContracts::cardinalities()` remains the executable source of
truth during the migration. Duplicate singletons are graph errors, optional
singletons may be absent but may not be duplicated, and ordered contributions
preserve module order while their aggregates reject duplicate semantic keys.

## Executable architecture laws

`tests/Support/Architecture/TellArchitectureRules.php` is the shared test
engine for the target dependency direction. Its production checks and invalid
fixtures enforce these rules without freezing directories to today's file
inventory:

- Data imports only Data and no framework container.
- Core contracts import only Data or Core.
- Core imports only Data and Core and no framework container.
- Adapters depend on public Data and Core boundaries rather than
  concrete providers.
- Capability families do not import sibling capability implementations.
- Capability provider files are passive when imported.
- Concrete capability construction is allowed in Composition or inside the
  provider's own family for private helpers.

The concrete-provider construction check now has no exceptions. P2.7 moved
Composer discovery, coding, ask-user, subagent, definition, and standard agent
selection into the standalone profile; the Core agent factory only applies the
ordered contributions it receives. Any construction outside Composition or a
provider's own private family fails immediately.

Exact-file assertions remain only for the intentionally closed root namespace,
whose sole public facade is `Tell.php`, and the closed top-level category set.
Data, Core, Adapter, Capability, and Composition internals are otherwise checked
by namespace, dependency direction, and construction behavior.
