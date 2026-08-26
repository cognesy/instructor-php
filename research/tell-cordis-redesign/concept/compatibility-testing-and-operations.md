# Compatibility, testing, and operations

## Compatibility contract

The migration preserves published Tell behavior for simple and controlled SDK
construction, synchronous and streaming runs, execution modes, workspace and
branch operations, catalogues, direct tools, budgets, cancellation, reasoning,
subagents, normalized events, protocol frames, CLI commands, and exits.

Public surface inventory must distinguish three categories:

- published types and behavior present in the latest package release;
- committed but not yet released APIs; and
- current uncommitted experiments.

The earlier SDK design explicitly committed only `Tell`, `TellRequest`,
`TellResult`, `TellEvent`, `TellProgress`, `TellConversation`, and
`TellWorkspace`. Current branch and control types need classification before
the redesign accidentally freezes every experiment.

## Characterization suite

Existing integration and feature tests are the behavioral oracle. Step 1 adds
a public behavior matrix, a per-command routing inventory, and explicit
characterization for event ordering, source attachment, redaction, protocol
terminal frames, and legacy session behavior.

Before module extraction, simplify synchronous execution to drain the streaming
path when that preserves public results. This reduces the migration matrix
without changing behavior.

## Contract conformance

Reusable conformance begins when a second implementation appears, not after
replaceability is advertised. The filesystem and in-memory workspaces share
tests for integrity, CAS, lineage, permissions, and failure atomicity.

Other suites cover model precedence and redaction, agent policy and
cancellation, normalized event order, and direct-tool approval and bounds.

## Architecture gates

Automated checks enforce:

- contracts import no Cordis, Symfony, filesystem implementation, or private
  provider implementation;
- the static kernel imports no standard modules or Tell domain behavior;
- modules do not import sibling implementations;
- CLI and protocol stay at the shell edge;
- standard wiring is the only production location naming every implementation;
  and
- every supported profile has a complete, duplicate-free graph.

## Performance baseline

The redesign must not turn a short CLI command into an expensive graph boot.
Step 1 records a repeatable CLI startup benchmark and counts workspace
discovery, definition scans, and Composer manifest scans for representative
operations.

Later steps compare against those counts. Request/host-local caching is added
only where it removes measured duplication without stale long-lived state.

## Package proof

CI packs any proposed package and installs it into a clean consumer using
public APIs only. Source boundaries precede package boundaries. The standard
distribution is also installed outside the monorepo so root autoloading and
path repositories cannot hide missing metadata.

## PHP and release matrix

Tell retains PHP `^8.3` and verifies supported dependency sets on PHP 8.3,
8.4, and 8.5. Repository package-split jobs should respect each package's
declared constraint rather than blindly treating an unsupported interpreter as
a product failure.

Adding the optional Cordis adapter does not raise Tell's PHP floor. Its release
gate includes a Packagist-resolvable tag and clean consumer resolution.

## Operational health

The static host exposes selected definitions, graph errors, active/disposed
state, and redacted startup/disposal failures.

The shell-job resource host exposes module state, missing capabilities, and
error classes. It does not expose instances, process handles, commands, output,
or Cordis contexts. Restart revisions, in-flight inference counts, and
reconciliation outcomes remain conditional supervisor concerns rather than
claims of the current host.

Health never reveals raw objects, prompts, credentials, branch content, or
arbitrary configuration.

## Public examples

`examples/D11_TellHarness` demonstrates SDK control and remains an acceptance
surface for both the static host and the opt-in persistent-shell resource host.
Its `GAPS.md` now records MCP lifecycle and cross-process durable shell sessions
as later candidates rather than claiming host-scoped shell jobs are absent.
