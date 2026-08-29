# Tell compatibility contract

This document defines the supported external behavior of
`cognesy/instructor-tell`. It is an acceptance inventory, not an inventory of
every public PHP symbol in `src/`.

The published baseline before this document is v2.8.5. v2.9.0 adds the
reasoning, execution, rendering, spill, and namespace changes recorded below.
The release keeps the PHP `^8.3` floor.

## Surface classification

<!-- markdownlint-disable MD013 -->
| Classification | Compatibility obligation |
| --- | --- |
| Published through v2.8.5 | Shipped by Packagist before v2.9.0. Preserve unless an explicit v2.9.0 migration below replaces it. |
| v2.9.0 | New or deliberately changed behavior in this release. It becomes the supported contract when v2.9.0 is published. |
| Internal | Implementation seams whose observable facade, CLI, persistence, or wire behavior may still be supported. Their concrete collaboration graph is not frozen. |
<!-- markdownlint-enable MD013 -->

The compatibility target remains PHP `^8.3`.

## Published PHP SDK

The supported SDK is organized by facade behavior. Result and projection value
objects returned by these facades are part of the same obligation.

<!-- markdownlint-disable MD013 -->
| Facade behavior | Published entry points | Characterization tests |
| --- | --- | --- |
| Open and execute | `Tell::open()`, `Tell::run()`, `Tell::runStream()`, `Data\TellRequest`, `Data\TellResult`, `Data\TellProgress` | `tests/Integration/SdkRuntimeTest.php`, `tests/Integration/ExecutionPolicyTest.php` |
| Durable conversation | `Tell::workspace()`, `Tell::conversation()`, `Workspace\TellWorkspace::initialize()`, `discover()`, `main()`, `conversation()`, and `Workspace\TellConversation` send/inspect/clear/compact operations | `tests/Integration/SdkRuntimeTest.php`, `tests/Integration/WorkspaceP0AcceptanceTest.php`, `tests/Integration/WorkspaceClearTest.php`, `tests/Integration/WorkspaceCompactionTest.php` |
| Request controls | agent, connection, model, tools, supplied answers, step and execution budgets, session, branch, branch overrides, transient/durable mode, and event listeners on `Data\TellRequest` | `tests/Integration/SdkRuntimeTest.php`, `tests/Integration/ExecutionPolicyTest.php`, `tests/Integration/AskUserTest.php` |
| Observation | `Data\TellEventEnvelope`, including `toArray()`, plus streamed `Data\TellProgress` checkpoints | `tests/Integration/SdkRuntimeTest.php`, `tests/Feature/RenderingTest.php` |
| Returned projections | `Data\TellConversationView`, `Data\TellContext`, `Data\TellClearResult`, `Data\TellCompactionResult`, `Data\TellWorkspaceInfo`, and `Data\TellExecutionMode` | `tests/Integration/WorkspaceInspectionTest.php`, `tests/Integration/WorkspaceContextTest.php`, `tests/Integration/WorkspaceClearTest.php`, `tests/Integration/WorkspaceCompactionTest.php` |
| Explicit policy and answers | `Configuration\TellExecutionPolicy` and `Capability\AskUser\TellAnswerQueue` when supplied through `Data\TellRequest` | `tests/Integration/ExecutionPolicyTest.php`, `tests/Integration/AskUserTest.php` |
<!-- markdownlint-enable MD013 -->

`Console\TellApplication`, `Console\TellCommand`, command classes, runtime
builders, stores, and Arena records are implementation seams rather than a
second SDK. Their
observable CLI and persistence behavior is supported as described below; their
constructors and internal collaboration graph are not frozen.

### Event compatibility boundary

`TellRequest::onEvent()` listeners receive a `TellEventEnvelope`, the same type
`CanObserveTellExecution::observe()` takes. It is the process-safe projection,
carrying the `tell.event.v1` schema used by event output and default execution
traces. Its schema, redaction, ordering, metadata bounds, and terminal semantics
are the normalized compatibility contract, and its key set and order are pinned
by `tests/Integration/EventEnvelopeShapeTest.php`. Other tests:
`tests/Feature/RenderingTest.php`, `tests/Integration/ExecutionTraceTest.php`,
and `tests/Integration/ToolCommandTest.php`.

There is no longer a raw framework event behind an observation. `TellEvent` and
its `source()` accessor are removed: the wrapper duplicated the envelope's
eleven fields in an untyped array, and `source()` was the one route by which an
unredacted tool result could be reached from a public listener. Nothing in the
repository called it. Code that deliberately wants the original typed Agents
event passes a `$prepareLoop` callable to `TellRuntime::run()`/`start()` and
wiretaps the loop directly, which yields the concrete event class rather than
the `object` that `source()` returned.

## v2.8.4 published additions

These additions are supported in v2.8.4 alongside the existing facade:

<!-- markdownlint-disable MD013 -->
| Published surface | Evidence |
| --- | --- |
| Branch/ref/config SDK: `TellBranches`, `TellBranch`, `TellRef`, `TellBranchConfiguration`, and returned branch values | `tests/Integration/SdkBranchControlTest.php`, `tests/Integration/SdkBranchRefTest.php`, `tests/Integration/SdkBranchConfigurationTest.php` |
| Catalogue and direct-tool SDK: `Tell::catalogue()`, `Tell::tools()`, `Discovery\TellCatalogue`, `Tool\TellTools`, `Data\TellToolRequest`, and `Data\TellToolResult` | `tests/Integration/SdkAgentCapabilitiesTest.php` |
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
the only source-level break introduced by v2.8.5; `Tell::run()` and
`Tell::runStream()` keep their signatures and their observable behavior.

## v2.9.0 source migration

v2.9.0 removes duplicate and transitional namespaces instead of shipping
deprecated wrappers for both the old and new architecture:

- request, result, event, tool, shell-job, diagnostic, and workspace projection
  DTOs are under `Cognesy\Tell\Data`;
- configuration policy and resolver classes are under
  `Cognesy\Tell\Configuration`;
- branch facades are under `Cognesy\Tell\Workspace\Branch`;
- shell-job hosting is under `Cognesy\Tell\Shell`, with payloads under `Data`;
- workspace implementation is grouped by `Arena`, `Branch`, `Compaction`,
  `Conversation`, `Execution`, and `Session`.

The top-level `Canonical`, duplicate top-level `Branch`, and `Resource`
namespaces are removed. `Legacy*` session compatibility classes and redundant
top-level DTO aliases are also removed. Applications importing those concrete
symbols must update their imports.

Typed reasoning is added in v2.9.0 through
`Data\TellRequest::reasoningEffort()` and Polyglot's
`Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort`. The duplicate
`Cognesy\Tell\TellReasoningEffort` enum does not exist in the v2.9.0 contract.
Evidence: `tests/Integration/ReasoningConfigurationTest.php`.

## v2.9.0 behavior changes: honest execution mode

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

`--output=human` is a turn output mode alongside `toon`, `text`, `json`, and
`events`. It renders the answer as Markdown for an ANSI terminal through
`Cognesy\Utils\Cli\CliMarkdown`, and falls back to the plain answer whenever
stdout is not decorated. `cognesy/instructor-tell` gains a direct
`cognesy/instructor-utils` requirement, which was already present transitively.
Evidence: `tests/Integration/HumanOutputTest.php`.

`output` is a new branch configuration key accepting the same five values, so
a workspace branch can set its own default turn format. An explicit `--output`
still wins, and the bundled value is the turn default; `tell config effective` now
reports `output` alongside the other branch/bundled values. Enum rejection
messages now name the offending key's own allowed values instead of always
naming the reasoning-effort set. Evidence:
`tests/Integration/HumanOutputTest.php`.

Turn progress is reported on two stderr channels. `-v` writes a readable
trace of each step, tool call, and result, with `-vvv` no longer abridging
bodies; `--debug` is a new flag writing one bracketed `key=value` line per
event. Both are channels rather than output modes, so stdout still carries
whichever `--output` format was requested and no existing mode changes; both
are refused together with `--quiet`.

`--debug` replaces what `--verbose` used to emit. Its lines keep the previous
`[tool.start] name=X` and `[tool.complete] name=X status=ok` prefixes and add
the step number, duration, call arguments, and results. `status` is now
`failed` when a tool returned its own failure envelope, where it previously
read `ok`. Payload values are valid JSON bounded to 512 bytes; an excerpt is
emitted as a JSON string with a companion `argsBytes`/`resultBytes` size.
`--debug` now also accompanies `json` and `events` output, which carried no
progress channel before.

`--output=human` is now the default turn format, where it was `toon`. A turn
invoked without `--output` renders its answer as Markdown on a terminal and as
the plain Markdown the model wrote anywhere else, so anything parsing TOON out
of a bare `tell "..."` must now pass `--output=toon`. The bundled branch
default moves with it, and `tell config effective` reports `human`. The other
formats, every explicit `--output`, and the no-prompt home screen are
unchanged. The no-prompt home screen gained a readable form and is what a bare
`tell` now prints: the same discovery as help rather than as a payload.
`--output=toon` and `--output=json` still return it as data, and `text` and
`events` are still refused by name, with the message now naming all three
accepted forms. Two commands may now declare the same option
with different defaults, which the application's routing definition tolerates
while the declarations parse alike. Evidence:
`tests/Feature/RenderingTest.php`, `tests/Integration/BusyIndicatorTest.php`.

`--output=human` on a terminal, with none of `-v`, `--debug`, or `--quiet`,
now writes a single self-erasing status line naming the current step, the
running tool, and elapsed time. It replaces that format's `[inference.start]`
heartbeat on a terminal only; redirected `human` stderr is unchanged, and
`toon`, `text`, and the structured formats are unchanged everywhere. The line
is erased on completion, failure, and cancellation alike, and is suspended
while a tool is asking the person a question. Animation forks a drawer process
when `pcntl`/`posix` are present and stderr is a real terminal, and falls back
to event-driven repainting otherwise. Evidence:
`tests/Integration/BusyIndicatorTest.php`.

Without any of this, `toon`, `text`, and redirected `human` keep their existing
`[inference.start] step=N` heartbeat and the structured formats keep writing
nothing.

Whenever a stderr channel wrote anything for a turn - a trace, machine lines,
or the bare heartbeat - one blank line is written to stderr before the answer,
so progress does not run straight into the result. It is placed on stderr
rather than stdout, so a redirected or piped stdout is byte-for-byte what it
was.

These two channels are the only Tell surfaces that show tool arguments and
results. Neither is persisted, neither enters the normalized `tell.event.v1`
stream, and neither reaches an execution trace file, so the redaction
guarantees of those surfaces are unchanged. Evidence:
`tests/Integration/StepTraceTest.php`,
`tests/Integration/MachineProgressTest.php`, `tests/Feature/RenderingTest.php`.

Tool output larger than `maxToolOutputChars` is now spilled rather than
truncated. Tell writes the whole result to a content-addressed blob under
`~/.tell/runtime/blobs/<project-hash>/`, and the step receives a stub naming
the blob, its size, a head preview, and a `read` call that resumes where the
preview stopped. This is on by default and reverses the previous behavior,
which discarded everything outside a head/tail window. Nothing is written into
the project itself, in any mode: a turn outside an initialized workspace still
persists nothing, and the coding tools are granted the store as an explicitly
readable path so the stub's read hint resolves.

It also writes raw tool output to disk by default, which the rest of Tell does
not do: traces exclude payloads unless asked, and normalized events carry none
at all. Blobs carry the payload in full. The store is created `0700` on first
write, nothing leaves the machine, and the blob is a plain file readable by
anything with filesystem access. Blobs are sharded on the first two characters
of the blob name, so no directory collects every result a project ever spilled.
Nothing prunes them, as with traces and sessions; reclaiming the space is the
operator's. Two new policy keys
govern it, on the same CLI/branch/project/user/bundled precedence as the rest:
`maxSpillBytes` (default 200,000, ceiling 5,000,000) is the per-result limit on
what reaches the disk and the switch - `0` restores head/tail truncation - and
`maxStubBytes` (default 2,000) is what reaches the conversation. A stub is
emitted whole regardless of `maxToolOutputChars`. A result that is not text is
stored under a `.bin` name with no preview and no read continuation. With
spilling on, the shell tool's own capture caps rise to the spill ceiling, so a
result reaches the hook intact. Evidence:
`tests/Integration/ToolOutputSpillTest.php`.

`WorkspaceRepository::initialize()` now keys on the schema record rather than the
bare `.tell` directory, matching what `discover()` has always done. Anything
else kept under that name - Tell's own storage root at `$HOME/.tell`, and
formerly a spill store - was read as an existing workspace, so `tell init`
failed with a broken-arena error and the directory could never be initialized.
Initialization remains idempotent and still refuses a genuinely malformed
workspace. Evidence: `tests/Integration/WorkspaceRepositoryTest.php`.

`Data\TellResult::executionMode()` is the published accessor for the same fact.
`Render\OutputRenderer::finish()` takes a `Data\TellExecutionMode` in place of its
`bool $transient` parameter; renderers are an implementation seam, so that is
not a published break. The `direct` tool-call execution projection is a
different path and is unchanged.

## CLI route inventory

The published v2.8.3 application has 19 Symfony command classes: 18 under
`src/Command/` plus `Console\TellCommand`. They register 20 routes because
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
| `tell` | `Console\TellCommand` | Published | `tests/Feature/CommandSurfaceTest.php`, `tests/Integration/SessionAndExitTest.php`, `tests/Integration/WorkspaceTurnTest.php` |
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
| `sessions` | `Command\SessionsCommand` | Published | `tests/Feature/CommandSurfaceTest.php`, `tests/Integration/SessionAndExitTest.php` |
| `tool` | `Command\ToolCommand` | Published | `tests/Integration/ToolCommandTest.php` |
| `tools` | `Command\ToolsCommand` | Published | `tests/Feature/CommandSurfaceTest.php` |
| `transcript` | `Command\WorkspaceInspectionCommand` | Published | `tests/Integration/WorkspaceInspectionTest.php` |
<!-- markdownlint-enable MD013 -->

## Persistence, wire, and process contracts

<!-- markdownlint-disable MD013 -->
| Boundary | Compatibility contract | Characterization tests |
| --- | --- | --- |
| Workspace marker and arena | Private `.tell/arena`, workspace schema 1, Arena record schema 1, immutable content-addressed objects, versioned refs, atomic compare-and-swap publication | `tests/Integration/WorkspaceRepositoryTest.php`, `tests/Integration/ArenaStoreTest.php`, `tests/Integration/ArenaStoreIntegrationTest.php`, `tests/Unit/ArenaRecordTest.php` |
| Branch selectors/config | Current-branch selector schema 1, ref schema 1, branch-config schema 1; secrets are forbidden and writes are version-checked | `tests/Integration/BranchCommandTest.php`, `tests/Integration/ArenaStoreIntegrationTest.php` |
| Named sessions | Named sessions are deterministic Arena refs with canonical session metadata; no alternate session store is consulted | `tests/Integration/SessionAndExitTest.php`, `tests/Integration/SdkRuntimeTest.php` |
| Normalized events | Monotonic payload-safe NDJSON using `tell.event.v1`; one normalized terminal outcome; raw typed source objects are not wire data | `tests/Feature/RenderingTest.php`, `tests/Integration/ExecutionTraceTest.php`, `tests/Integration/ToolCommandTest.php` |
| Execution traces | Private JSONL derived from normalized events, payloads excluded by default, credentials always redacted, trace failure does not fail the run | `tests/Integration/ExecutionTraceTest.php`, `tests/Unit/TracePayloadTest.php` |
| One-run protocol | Request `tell.agent.request.v1` and frame `tell.agent.frame.v1`; bounded input/output, monotonic sequence, exactly one terminal frame | `tests/Integration/AgentProtocolTest.php` |
| Rendering | Human default plus explicit toon, text, JSON, and event modes; structured usage errors remain on stdout | `tests/Feature/RenderingTest.php`, `tests/Feature/CommandSurfaceTest.php` |
| Spilled tool output | Blobs in Tell's storage at `~/.tell/runtime/blobs/<project-hash>/<ab>/`, content-addressed and shard-fanned, never under the project; a stub names the blob, previews its head within `maxStubBytes`, and carries a `read` continuation unless the result is binary; `maxSpillBytes` of `0` restores head/tail truncation | `tests/Integration/ToolOutputSpillTest.php` |
| Process exits | `0` completed, `1` failed/stopped runtime, `2` invalid usage; protocol cancellation is `130` | `tests/Integration/SessionAndExitTest.php`, `tests/Feature/CommandSurfaceTest.php`, `tests/Integration/AgentProtocolTest.php` |
<!-- markdownlint-enable MD013 -->

Changing a schema identifier, persisted meaning, redaction guarantee, route,
exit meaning, or published facade behavior requires an explicit compatibility
decision and a test update. Refactoring the internal graph does not.

## Named session decision

Named Tell sessions have one persistence model: canonical records selected by
deterministic refs in an initialized workspace Arena. Tell does not discover,
import, update, or delete records from an alternate JSON session store.
