# Tell compatibility contract

This document defines the supported external behavior of
`cognesy/instructor-tell`. It is an acceptance inventory, not an inventory of
every public PHP symbol in `src/`.

The `v2.8.3` baseline resolves to commit
`d85dece468e3570bf6af58ecbb1bf5280895d356` for Tell. The v2.8.4 release
promoted the additions recorded below into the supported 2.x contract. This
v2.8.5 release adds the run-handle surface and states the streaming durability
contract explicitly.

## Surface classification

<!-- markdownlint-disable MD013 -->
| Classification | Compatibility obligation |
| --- | --- |
| Published | Shipped by Packagist as `cognesy/instructor-tell` v2.8.4. Preserve within the 2.x line unless a documented deprecation says otherwise. |
| Committed unreleased | None at the v2.8.4 release cut. Additions in this class must be named here before release. |
| Worktree experiment | Intentional, tested work that is not committed or released. It may change while the current redesign is in progress. It becomes supported only when moved to the published or committed-unreleased class. |
<!-- markdownlint-enable MD013 -->

The compatibility target is PHP `^8.3`. The current redesign must not silently
raise that floor.

## Published PHP SDK

The supported SDK is organized by facade behavior. Result and projection value
objects returned by these facades are part of the same obligation.

<!-- markdownlint-disable MD013 -->
| Facade behavior | Published entry points | Characterization tests |
| --- | --- | --- |
| Open and execute | `Tell::open()`, `Tell::run()`, `Tell::runStream()`, `TellRequest`, `TellResult`, `TellProgress` | `tests/Integration/SdkRuntimeTest.php`, `tests/Integration/ExecutionPolicyTest.php` |
| Durable conversation | `Tell::workspace()`, `Tell::conversation()`, `TellWorkspace::initialize()`, `discover()`, `main()`, `conversation()`, and `TellConversation` send/inspect/clear/compact operations | `tests/Integration/SdkRuntimeTest.php`, `tests/Integration/WorkspaceP0AcceptanceTest.php`, `tests/Integration/WorkspaceClearTest.php`, `tests/Integration/WorkspaceCompactionTest.php` |
| Request controls | agent, connection, model, tools, supplied answers, step and execution budgets, session, branch, branch overrides, transient/durable mode, and event listeners on `TellRequest` | `tests/Integration/SdkRuntimeTest.php`, `tests/Integration/ExecutionPolicyTest.php`, `tests/Integration/AskUserTest.php` |
| Observation | `TellEvent`, including `source()` and `envelope()`, plus streamed `TellProgress` checkpoints | `tests/Integration/SdkRuntimeTest.php`, `tests/Feature/RenderingTest.php` |
| Returned projections | `TellConversationView`, `TellContext`, `TellClearResult`, `TellCompactionResult`, `TellWorkspaceInfo`, and `TellExecutionMode` | `tests/Integration/WorkspaceInspectionTest.php`, `tests/Integration/WorkspaceContextTest.php`, `tests/Integration/WorkspaceClearTest.php`, `tests/Integration/WorkspaceCompactionTest.php` |
| Explicit policy and answers | `Runtime\TellExecutionPolicy` and `Capability\AskUser\TellAnswerQueue` when supplied through `TellRequest` | `tests/Integration/ExecutionPolicyTest.php`, `tests/Integration/AskUserTest.php` |
<!-- markdownlint-enable MD013 -->

`TellApplication`, `TellCommand`, command classes, runtime builders, stores, and
canonical records are implementation seams rather than a second SDK. Their
observable CLI and persistence behavior is supported as described below; their
constructors and internal collaboration graph are not frozen.

### Event compatibility boundary

`TellEvent::source()` exposes the original typed Agents event to in-process PHP
listeners. It is source-level compatibility only: consumers may inspect the
object, but must not serialize it or treat its concrete object layout as a Tell
wire schema.

`TellEvent::envelope()` is the process-safe projection. It has the same
`tell.event.v1` schema used by event output and default execution traces. Its
schema, redaction, ordering, metadata bounds, and terminal semantics are the
normalized compatibility contract. Tests: `tests/Feature/RenderingTest.php`,
`tests/Integration/ExecutionTraceTest.php`, and
`tests/Integration/ToolCommandTest.php`.

## v2.8.4 published additions

These additions are supported in v2.8.4 alongside the existing facade:

<!-- markdownlint-disable MD013 -->
| Published surface | Evidence |
| --- | --- |
| Branch/ref/config SDK: `TellBranches`, `TellBranch`, `TellRef`, `TellBranchConfiguration`, and returned branch values | `tests/Integration/SdkBranchControlTest.php`, `tests/Integration/SdkBranchRefTest.php`, `tests/Integration/SdkBranchConfigurationTest.php` |
| Catalogue and direct-tool SDK: `Tell::catalogue()`, `Tell::tools()`, `TellCatalogue`, `TellTools`, `TellToolRequest`, and `TellToolResult` | `tests/Integration/SdkAgentCapabilitiesTest.php` |
| Typed reasoning control: `TellReasoningEffort` and `TellRequest::reasoningEffort()` | `tests/Integration/ReasoningConfigurationTest.php` |
| Deterministic test harness: `Tell::testing()` and `Testing\TellTestFactory` | `tests/Integration/SdkTestingHarnessTest.php` |
| Controlled cancellation/output behavior added to the SDK | `tests/Integration/SdkControlledRunTest.php` |
| One-run external controller boundary: `agent --rpc`, `tell.agent.request.v1`, and `tell.agent.frame.v1` | `tests/Integration/AgentProtocolTest.php` |
<!-- markdownlint-enable MD013 -->

These additions widen existing CLI behavior; they do not
make workspace store classes, command constructors, or runtime factories part
of the supported facade.

## v2.8.5 published additions

<!-- markdownlint-disable MD013 -->
| Published surface | Evidence |
| --- | --- |
| Run handle SDK: `Tell::start()`, `TellRuntime::start()`, `CanRunTell::start()`, and `Runtime\TellRun` with `checkpoints()`, `result()`, `isCommitted()`, `diagnostics()`, and `wait()` | `tests/Integration/StreamCommitContractTest.php` |
| Streaming durability contract: observing a completed checkpoint implies the durable turn is published; a run abandoned before any publishable checkpoint publishes nothing and records the `run_abandoned` diagnostic | `tests/Integration/StreamCommitContractTest.php`, `tests/Integration/SdkRuntimeTest.php` |
<!-- markdownlint-enable MD013 -->

`Runtime\TellRunOutcome` is the mechanism behind the handle, not a second SDK:
it is populated by runners and read through `TellRun`.

Implementers of `CanRunTell` outside this package must add `start()`. That is
the only source-level break in this release; `Tell::run()` and
`Tell::runStream()` keep their signatures and their observable behavior.

## Committed unreleased behavior change: honest execution mode

`execution.mode` in JSON and TOON turn output now reports what the turn
actually persisted, and gains a third value:

<!-- markdownlint-disable MD013 -->
| `execution.mode` | `execution.durable` | Turn |
| --- | --- | --- |
| `durable` | `true` | Published an immutable arena turn, or saved a named session. |
| `transient` | `false` | Ran with the workspace context but wrote no conversation or session state. |
| `stateless` | `false` | Ran outside any initialized workspace with no named session (new value). |
<!-- markdownlint-enable MD013 -->

Through v2.8.5 both fields were derived from the transient flag alone, so a
stateless turn — the default outside a `.tell/` project — reported
`mode: durable` and `durable: true` while persisting nothing. Consumers that
branched on `execution.mode === 'durable'` must now also accept `stateless`.
`execution.durable` keeps its meaning and is now correct for stateless turns.
Evidence: `tests/Integration/ExecutionModeReportingTest.php`.

`TellResult::executionMode()` is the published accessor for the same fact.
`Render\OutputRenderer::finish()` takes a `TellExecutionMode` in place of its
`bool $transient` parameter; renderers are an implementation seam, so that is
not a published break. The `direct` tool-call execution projection is a
different path and is unchanged.

## CLI route inventory

The published v2.8.3 application has 19 Symfony command classes: 18 under
`src/Command/` plus `TellCommand`. They register 20 routes because
`WorkspaceInspectionCommand` supplies both `history` and `transcript`.
v2.8.4 adds `AgentCommand`, producing 20 classes and 21 routes.

All named routes retain the custom routing rules characterized by
`tests/Feature/CommandSurfaceTest.php`: an implicit prompt routes to `tell`, a
known subcommand may follow global options, and tokens following `--` remain
prompt content. `planes` is the typed operator projection of the same command
instances, rather than a separately maintained catalogue.

<!-- markdownlint-disable MD013 -->
| Route | Class | Status | Behavior tests |
| --- | --- | --- | --- |
| `tell` | `TellCommand` | Published | `tests/Feature/CommandSurfaceTest.php`, `tests/Feature/SessionAndExitTest.php`, `tests/Integration/WorkspaceTurnTest.php` |
| `agent` | `Command\AgentCommand` | Published | `tests/Integration/AgentProtocolTest.php` |
| `agents` | `Command\AgentsCommand` | Published | `tests/Feature/CommandSurfaceTest.php` |
| `auth` | `Command\AuthCommand` | Published | `tests/Integration/CredentialManagementTest.php` |
| `branch` | `Command\BranchCommand` | Published | `tests/Integration/BranchCommandTest.php` |
| `checkout` | `Command\CheckoutCommand` | Published | `tests/Integration/BranchCommandTest.php` |
| `clear` | `Command\ClearCommand` | Published | `tests/Integration/WorkspaceClearTest.php` |
| `compact` | `Command\CompactCommand` | Published | `tests/Integration/WorkspaceCompactionTest.php` |
| `config` | `Command\ConfigCommand` | Published | `tests/Integration/BranchCommandTest.php` |
| `context` | `Command\ContextCommand` | Published | `tests/Integration/WorkspaceContextTest.php` |
| `describe` | `Command\DescribeCommand` | Published | `tests/Feature/CommandSurfaceTest.php` |
| `history` | `Command\WorkspaceInspectionCommand` | Published | `tests/Integration/WorkspaceInspectionTest.php` |
| `init` | `Command\InitCommand` | Published | `tests/Integration/WorkspaceCommandTest.php` |
| `models` | `Command\ModelsCommand` | Published | `tests/Integration/ProviderCatalogueCommandTest.php` |
| `planes` | `Command\PlanesCommand` | Published | `tests/Feature/CommandSurfaceTest.php` |
| `providers` | `Command\ProvidersCommand` | Published | `tests/Integration/ProviderCatalogueCommandTest.php` |
| `reset` | `Command\ResetCommand` | Published | `tests/Integration/BranchCommandTest.php` |
| `sessions` | `Command\SessionsCommand` | Published | `tests/Feature/CommandSurfaceTest.php`, `tests/Feature/SessionAndExitTest.php` |
| `tool` | `Command\ToolCommand` | Published | `tests/Integration/ToolCommandTest.php` |
| `tools` | `Command\ToolsCommand` | Published | `tests/Feature/CommandSurfaceTest.php` |
| `transcript` | `Command\WorkspaceInspectionCommand` | Published | `tests/Integration/WorkspaceInspectionTest.php` |
<!-- markdownlint-enable MD013 -->

## Persistence, wire, and process contracts

<!-- markdownlint-disable MD013 -->
| Boundary | Compatibility contract | Characterization tests |
| --- | --- | --- |
| Workspace marker and arena | Private `.tell/arena`, workspace schema 1, canonical schema 1, immutable content-addressed objects, versioned refs, atomic compare-and-swap publication | `tests/Integration/WorkspaceManagerTest.php`, `tests/Integration/ArenaStoreTest.php`, `tests/Integration/ArenaStoreIntegrationTest.php`, `tests/Unit/CanonicalRecordTest.php` |
| Branch selectors/config | Current-branch selector schema 1, ref schema 1, branch-config schema 1; secrets are forbidden and writes are version-checked | `tests/Integration/BranchCommandTest.php`, `tests/Integration/ArenaStoreIntegrationTest.php` |
| Legacy sessions | Existing Tell session JSON is a read-only import source; canonical arena history becomes authoritative after a successful first-use migration | `tests/Feature/SessionAndExitTest.php`, `tests/Integration/WorkspaceSessionCompatibilityTest.php` |
| Normalized events | Monotonic payload-safe NDJSON using `tell.event.v1`; one normalized terminal outcome; raw typed source objects are not wire data | `tests/Feature/RenderingTest.php`, `tests/Integration/ExecutionTraceTest.php`, `tests/Integration/ToolCommandTest.php` |
| Execution traces | Private JSONL derived from normalized events, payloads excluded by default, credentials always redacted, trace failure does not fail the run | `tests/Integration/ExecutionTraceTest.php`, `tests/Unit/TracePayloadTest.php` |
| One-run protocol | Request `tell.agent.request.v1` and frame `tell.agent.frame.v1`; bounded input/output, monotonic sequence, exactly one terminal frame | `tests/Integration/AgentProtocolTest.php` |
| Rendering | TOON default plus explicit text, JSON, and event modes; structured usage errors remain on stdout | `tests/Feature/RenderingTest.php`, `tests/Feature/CommandSurfaceTest.php` |
| Process exits | `0` completed, `1` failed/stopped runtime, `2` invalid usage; protocol cancellation is `130` | `tests/Feature/SessionAndExitTest.php`, `tests/Feature/CommandSurfaceTest.php`, `tests/Integration/AgentProtocolTest.php` |
<!-- markdownlint-enable MD013 -->

Changing a schema identifier, persisted meaning, redaction guarantee, route,
exit meaning, or published facade behavior requires an explicit compatibility
decision and a test update. Refactoring the internal graph does not.

## Legacy session decision

Decision dated 2026-08-26: retain legacy Tell session JSON discovery and
read-only first-use migration for the complete 2.x release line.

- Never rewrite or delete the legacy source during compatibility import.
- After migration, canonical arena history is authoritative; divergence is a
  warning, not an implicit re-import.
- Review usage and maintenance cost on 2027-03-01. That review may retain the
  importer longer, but cannot remove it from a 2.x release.
- Removal is permitted only in Tell 3.0 or later, after at least one minor
  release documents deprecation and provides an explicit migration command or
  guide.

The decision is characterized by
`tests/Integration/WorkspaceSessionCompatibilityTest.php`.
