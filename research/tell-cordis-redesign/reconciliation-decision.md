# Supervised reconciliation decision

## Decision

**No-go for the current Tell release.** Do not add a supervisor, YAML loader,
admission gate, in-flight counter, watcher, or reconciliation API after the
shell-job resource host.

This is a product-scope decision, not a limitation of Cordis. The implemented
feature needs deterministic ownership and reverse cleanup, which Cordis now
provides. It does not need live replacement.

## Evidence

<!-- markdownlint-disable MD013 -->
| Decision question | Current evidence | Result |
| --- | --- | --- |
| Must a configured resource survive a policy/configuration change? | Shell-job policy and approval are immutable host inputs; each job receives immutable bounds at `start()` | No demonstrated operation |
| Are there dependent resource modules that need selective restart? | The host has one `shell.jobs` manager and independent child process scopes; jobs have no replaceable provider dependency | No |
| Is inference execution supervised by this host? | `Tell::open()` and `TellHost::standard()` remain Cordis-free; inference generators and resource jobs have separate owners | No |
| Does a watcher or declarative live configuration source exist? | No CLI, SDK, worker, YAML, polling, or filesystem-watch contract requests live changes | No |
| Is rebuilding too expensive or operationally unsafe? | The reusable builder test disposes one host, boots a fresh runtime, and runs a new job; the D11 full lifecycle completes in well under one second on the reference machine | No evidence |
| Would reconciliation preserve a resource that rebuild cannot? | Rebuild intentionally cancels host-owned processes; no current workflow promises that jobs survive owner replacement | No |
<!-- markdownlint-enable MD013 -->

Agents' `AgentExecutionAbandoned` event remains a useful correctness primitive
if Tell later supervises inference streams. It makes bounded safe-point counting
possible; it does not by itself create demand for live reconciliation.

## Operational path

Applications select immutable static modules before `TellHost::boot()`. To
change ordinary runtime composition, finish or cancel current work, dispose the
host, and boot a fresh graph from factories.

Applications select shell-job policy and approval before
`TellResourceHostBuilder::boot()`. To change them, stop admitting application
requests, dispose the resource host (which cancels its jobs), and boot a fresh
host. Reusing the immutable builder is safe and creates an isolated runtime;
disposed job IDs and manager handles do not become live again.

Queue and framework workers should rebuild at their existing job, request, or
worker-recycle boundary. This provides a visible, testable cutover rather than
inventing an in-process configuration control plane.

## Revisit triggers

Reopen supervised reconciliation only when at least one measured workflow
cannot meet its operational requirement through bounded host rebuild:

1. a resident MCP or similar transport must rotate a dependency without
   dropping unrelated live sessions;
2. two or more dependent resource modules need selective restart while
   unrelated scopes stay active;
3. a long-lived queue worker has a measured rebuild/recovery SLO that current
   disposal and boot cannot meet; or
4. an approved declarative configuration source needs last-known-good live
   application and has a named operator and failure policy.

If a trigger is met, Step 10's existing constraints remain mandatory:
allowlisted typed factories, complete pre-validation, zero-in-flight safe
points, bounded busy failure, fresh instances, redacted health, and no hot
workspace or observer claims in the first increment.
