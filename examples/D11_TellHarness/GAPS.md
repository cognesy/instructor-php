# Tell Harness and fyai: Capability Gap Analysis

Tell now has a clean PHP turn API: immutable requests, typed results and
observations, generator-based completed-step progress, project-local durable
conversations and branches, transient runs, explicit compaction/clear controls,
branch-local runtime intent, budgets, cancellation, and bounded delegation.
The D11 examples exercise the core SDK surface.

That is not yet the same operating model as fyai. fyai is a daemonless agent
workspace with branch-local configuration, named immutable roots, an event
pump, a controller protocol, and an explicit tool/job model. The table records
the material differences observed in fyai's `doc/` and current Tell public
sources. It does not promise a feature that Tell does not presently expose.

<!-- markdownlint-disable MD013 -->
| Area | fyai model | Tell today | Consequence for a PHP harness | Tracked direction |
| --- | --- | --- | --- | --- |
| Independent lines of work | Named hierarchical branches, selection, and symbolic historical references ([branching](../../../../_agents/fyai/doc/branching.md)) | Named Tell branches support creation, checkout, per-run selection, and non-destructive reset | The current SDK examples still demonstrate conversations rather than branch handles; history has no public frozen root handles | Branch operations are implemented in P1.1–P1.3; immutable root handles remain P2 scope |
| Reproducible inspection | Immutable root handles make an automation read a frozen arena state | `history()` returns a current bounded view | Concurrent callers cannot pin one durable snapshot for a multi-read workflow | Deliberately beyond current P1; retain as P2 scope |
| Conversation configuration | Each fyai branch stores model/API/tool/reasoning intent | Tell branches persist typed non-secret runtime intent; `config effective` reports precedence and provenance | Reasoning intent is only available when the current Polyglot preset exposes it; consumers still cannot pin a provider-native opaque state | P1.4–P1.5 are implemented |
| Runtime control | Event pump owns cancellation, deadlines, and concurrent operation cleanup ([event pump](../../../../_agents/fyai/doc/event-pump-architecture.md)) | Tell has retry/deadline/output/tool-call budgets, cooperative cancellation, and one bounded terminal outcome | A controller can cancel a run but cannot pause/resume it or coordinate concurrent delegated work | P1.6–P1.7 are implemented; pause/resume and concurrent scheduling remain out of scope |
| Stable observability | fyai separates operational output from canonical transcript and treats progressive display as a document ([display semantics](../../../../_agents/fyai/doc/display-output-semantics.md)) | Tell emits bounded, redacted `tell.event.v1` envelopes; trace JSONL and canonical transcript remain separate | Consumers cannot receive a progressive Markdown/document stream | P1.7 is implemented; progressive Markdown remains intentionally out of scope |
| External control | `fyai agent --rpc` exposes a one-run JSON-RPC sub-agent protocol ([agent protocol](../../../../_agents/fyai/doc/agent-protocol.md)) | PHP in-process calls and CLI output only | A supervisor cannot spawn/control Tell through a stable process protocol | Not in current P1; consider only if an external controller is a real product need |
| Tool/job model | fyai defines shell jobs, cancellation ownership, MCP process lifecycle, and documented tool gaps ([tool gap analysis](../../../../_agents/fyai/doc/tool-gap-analysis.md)) | Tell exposes canonical coding-tool aliases, direct `tell tool` dispatch, and bounded non-interactive `ask_user` | Tell has no durable shell job/session or approval API, and no MCP lifecycle | P1.8–P1.10 are implemented; persistent shell sessions and MCP remain later scope |
| Delegated work | fyai persists child work with explicit parent/child lifetime rules | Tell persists sequential depth-one child work on scoped `agent-*` branches with fork/fresh context | No controller protocol, recursive delegation, or concurrent scheduling | P1.11 is implemented; the remaining package-isolation gate is release composition |
| Provider-native compaction | fyai selects provider-native compaction where available, with a fallback ([context compaction](../../../../_agents/fyai/doc/context-compaction.md)) | Tell has an explicit model-summary compaction operation | Long conversations cannot preserve provider-native opaque compaction artifacts | Not a current Tell P1 commitment; assess only after branch config/catalogue work |
| Test ergonomics | fyai documents an executable process-level operating model | Tell's public `Tell::open()` accepts an internal factory, but its test factory is package-test support | A consumer lacks a small supported fake-driver/harness factory for deterministic SDK tests | Add a public testing adapter or documented consumer test recipe after P1.12 validates the packaged SDK |
<!-- markdownlint-enable MD013 -->

## Recommended operating model now

Use Tell for in-process PHP work that benefits from one of the D11 shapes:
stateless jobs, observable completed steps, explicit durable conversations,
safe transient investigations, explicit context reduction, branch-local intent,
and sequential delegated work. Use Tell's execution policy and normalized event
envelopes rather than reconstructing retry, deadline, cancellation, or default
redaction policy in the caller.

Use Tell branches for independent lines of work; do not overload
`conversation()` names for that purpose. Branches carry configuration and
ancestry selection and support non-destructive reset, but intentionally have no
public reflog or merge/rebase workflow.

## What should change first

The first remaining high-leverage capability is a stable external controller
protocol and a supported consumer test harness. The implemented P1 controls
make an HTTP or queue controller safe to build, but Tell deliberately does not
yet provide fyai's JSON-RPC process protocol, pause/resume model, public root
handles, or concurrent delegated scheduling.

The detailed design and dependency order live in
[research/tell-roadmap.md](../../research/tell-roadmap.md); the linked Beads
P1 acceptance remains open only until the coordinated release updates Tell's
isolated package dependencies to the new Agents and Polyglot APIs.
