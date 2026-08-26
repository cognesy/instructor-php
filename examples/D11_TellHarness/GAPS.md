# Tell Harness and fyai: Capability Gap Analysis

Tell now has a clean PHP turn API plus a bounded external process boundary:
immutable requests, typed results and observations, deterministic test drivers,
generator-based completed-step progress, project-local durable conversations,
branch and immutable-ref handles, transient runs, explicit compaction/clear and
reset controls, branch-local runtime intent, typed reasoning effort, budgets,
cancellation, bounded delegation, direct tool dispatch, and a versioned one-run
JSONL protocol. The D11 examples exercise each of those public surfaces.

That is still not the same operating model as fyai. fyai has broader branch
operations and a resident event-pump model. Tell now has one explicit
long-lived resource model for host-scoped shell jobs, but not persistent shell
sessions or MCP servers. The table records the material differences observed in
fyai's `doc/` and current Tell public sources. It does not promise a feature
that Tell does not presently expose.

<!-- markdownlint-disable MD013 -->
| Area | fyai model | Tell today | Consequence for a PHP harness | Tracked direction |
| --- | --- | --- | --- | --- |
| Independent lines of work | Named hierarchical branches, selection, and symbolic historical references ([branching](../../../../_agents/fyai/doc/branching.md)) | Named Tell branches support create, list/show, checkout, per-run selection, non-destructive reset, and typed PHP handles | Tell has no hierarchy, merge/rebase, deletion, or public reflog/recovery operation | Keep those mutations out until a concrete multi-writer workflow justifies their conflict semantics |
| Reproducible inspection | Immutable root handles make an automation read a frozen arena state | `TellBranch::pin()` returns a verified `TellRef`; bounded history, transcript, and context reads stay fixed after branch movement | Essential frozen reads are aligned; Tell does not expose fyai's broader named-root vocabulary | No essential gap for current automation |
| Conversation configuration | Each fyai branch stores model/API/tool/reasoning intent | Tell branches persist typed non-secret runtime intent; `config effective` reports precedence and provenance | Reasoning intent is only available when the current Polyglot preset exposes it; consumers still cannot pin a provider-native opaque state | P1.4–P1.5 are implemented |
| Runtime control | Event pump owns cancellation, deadlines, and concurrent operation cleanup ([event pump](../../../../_agents/fyai/doc/event-pump-architecture.md)) | Tell has retry/deadline/output/tool-call budgets, cooperative cancellation, and one bounded terminal outcome | A controller can cancel a run but cannot pause/resume it or coordinate concurrent delegated work | P1.6–P1.7 are implemented; pause/resume and concurrent scheduling remain out of scope |
| Stable observability | fyai separates operational output from canonical transcript and treats progressive display as a document ([display semantics](../../../../_agents/fyai/doc/display-output-semantics.md)) | Tell emits bounded, redacted `tell.event.v1` envelopes; trace JSONL and canonical transcript remain separate | Consumers cannot receive a progressive Markdown/document stream | P1.7 is implemented; progressive Markdown remains intentionally out of scope |
| External control | `fyai agent --rpc` exposes a one-run JSON-RPC sub-agent protocol with initialize/run/shutdown messages ([agent protocol](../../../../_agents/fyai/doc/agent-protocol.md)) | `tell agent --rpc` accepts one `tell.agent.request.v1` and emits ordered `tell.agent.frame.v1` JSONL with one terminal outcome | External supervisors can launch and observe one run, but cannot keep a negotiated resident channel, answer questions mid-run, pause, or resume | The essential one-run boundary is implemented; add bidirectional lifecycle only for a demonstrated controller need |
| Tool/job model | fyai defines shell jobs, cancellation ownership, MCP process lifecycle, and documented tool gaps ([tool gap analysis](../../../../_agents/fyai/doc/tool-gap-analysis.md)) | Tell exposes direct tool dispatch plus opt-in, approved, bounded, host-scoped shell jobs with cursored output and deterministic cleanup | Jobs survive the `start()` call but not their PHP host; Tell has no durable cross-process shell session or MCP lifecycle | Host-scoped persistent jobs are implemented; durable sessions and MCP remain later scope |
| Delegated work | fyai persists child work with explicit parent/child lifetime rules | Tell persists sequential depth-one child work on scoped `agent-*` branches with fork/fresh context | No recursive delegation, concurrent scheduling, or individually addressable child cancellation | Essential sequential delegation and isolated package composition are implemented; expand only with explicit scheduler ownership |
| Provider-native compaction | fyai selects provider-native compaction where available, with a fallback ([context compaction](../../../../_agents/fyai/doc/context-compaction.md)) | Tell has an explicit model-summary compaction operation | Long conversations cannot preserve provider-native opaque compaction artifacts | Not a current Tell P1 commitment; assess only after branch config/catalogue work |
| Test ergonomics | fyai documents an executable process-level operating model | `Tell::testing()` and `TellTestFactory` run the real orchestration around a deterministic public driver; protocol tests execute a child process | Essential PHP and process-boundary testing are aligned; callers still own fixture state and cleanup | No essential gap for current automation |
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

The high-value remaining gaps are narrower than the original audit. Tell can
now start a bounded command, inspect its output later, cancel it, and dispose it
through an explicit resource owner. It does not persist jobs across PHP process
restarts and does not yet expose those jobs as an agent tool. An MCP lifecycle
would make external tool servers discoverable and explicitly owned, but brings
transport, authentication, discovery, and protocol-version policy that the
first lifecycle increment intentionally avoided.

After those, evaluate concurrent delegated scheduling and a bidirectional
resident controller only against real workloads. Pause/resume, progressive
Markdown display, provider-native opaque compaction, and merge/rebase are lower
priority because Tell's current one-run protocol, frozen refs, sequential child
branches, and explicit model-summary compaction already cover the essential
automation loop.

The detailed historical design and dependency order live in
[research/tell-roadmap.md](../../research/tell-roadmap.md). The acceptance and
release-composition work described there is complete through the bounded P2
external protocol; the remaining gaps above are future product decisions, not
implied commitments.
