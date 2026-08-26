# Tell modular redesign completion matrix

This matrix is the close-out index for Beads epic `instructor-ll1t`. It maps
each ordered plan step and reviewed feedback decision to current, executable
evidence. Beads close reasons retain the command-by-command results recorded
during delivery.

## Ordered plan

<!-- markdownlint-disable MD013 -->
| Step | Outcome | Authoritative implementation evidence | Verification evidence | Beads |
| --- | --- | --- | --- | --- |
| 1. Freeze behavior and baseline | Published/current APIs, 21 routes, persistence/events/exits, legacy horizon, startup/discovery budgets, PHP 8.3-8.5 matrix | `packages/tell/COMPATIBILITY.md`, `STARTUP_BASELINE.md`, split workflow | command-surface, compatibility, startup-baseline tests and benchmark | `.1`, `.2` |
| 2. Simplify runtime and global state | Sync drains stream; abandonment leaves durable head unpublished; no Tell/Agents `putenv`; one Tell environment owner; discovery failures are visible | `TellRuntime`, `TellPaths`, discovery diagnostics, Agents abandonment event | runtime, SDK, event, discovery, startup tests | `.3`, `.4` |
| 3. Contracts and static composition | Honest capability interfaces/cardinality; factory definitions; pre-effect graph admission; typed host accessors; exhaustive reverse cleanup; no container | `CONTRACTS.md`, `STATIC_COMPOSITION_DECISION.md`, `HOST.md`, `src/Contracts`, `src/Composition`, `TellHost*` | architecture, primitive-decision, and host tests | `.5`, `.6`, `.7` |
| 4. Agent and provider runtime | Explicit path, secret, model, agent, clock, cancellation, and execution modules; one resolved immutable model; deterministic driver replacement | `StandardTellProfile`, `Runtime/Standard*`, `DefaultTellRunner` | standard-host and single-resolution tests | `.8` |
| 5. Workspace and conversations | Cohesive filesystem module plus process-local in-memory implementation over a shared arena contract | `FilesystemTellWorkspaceModule`, `InMemoryTellWorkspaceManager`, `CanUseTellArena` | shared filesystem/in-memory conformance suite | `.9` |
| 6. Configuration, tools, discovery, observation | Provenance-aware configuration; descriptive Composer extensions; controlled direct tools; normalized redacted observation and PSR adapter | configuration/extension/tool/observation modules and public contracts | module, direct-tool, reasoning, observation, redaction tests | `.10`, `.11`, `.12` |
| 7. CLI, protocol, SDK wiring | Commands, Symfony app, SDK, and bounded one-run JSONL protocol share the standard host | `commands.core`, `application.symfony`, `protocol.one-run`, `Tell::open`, `bin/tell` | 21-route surface, console-host, SDK, protocol, signal tests; D11 `ModularHost` | `.13`, `.14` |
| 8. Replacement and package proof | External implementations replace advertised seams; one-package Tell distribution installs without monorepo paths | architecture and conformance tests; `scripts/clean-consumer.sh`; package-boundary decision | PHP 8.3/8.4/8.5 x lowest/highest artifact installs, SDK and CLI smoke | `.15` |
| 9. Scoped resource lifecycle | Host-scoped persistent shell jobs; default-deny approval; bounded process groups/output; Cordis-owned child scopes; redacted health/events; ordinary hosts remain Cordis-free | `TellResourceHost*`, `Resource/*`, `persistent-shell-jobs.md`, scoped conformance, D11 `PersistentShellJobs` | 14 lifecycle tests/59 assertions, D11 run, clean artifact smoke; Cordis v1.0.1 Packagist/release/CI gate | `.16`-`.19` |
| 10. Supervised reconciliation | Evidence-backed no-go; deterministic rebuild is sufficient; conditional constraints and revisit triggers remain documented | `reconciliation-decision.md`, Step 10 disposition | fresh builder reboot test, API/source no-claim audit, architecture prohibition | `.20`, `.21` |
<!-- markdownlint-enable MD013 -->

## Team feedback disposition

<!-- markdownlint-disable MD013 -->
| Reviewed item | Final disposition and evidence |
| --- | --- |
| Separate static composition from lifecycle | Implemented as independent `TellHost` and opt-in `TellResourceHost`; ordinary SDK/CLI never load Cordis |
| Retain PHP 8.3 | Package constraint remains `^8.3`; split and clean-consumer matrices cover PHP 8.3, 8.4, and 8.5 |
| Honest contract dependencies | Contracts reuse stable public Agents/Polyglot values and architecture tests reject framework/private implementation leakage |
| Safe points instead of leases | No lease was introduced; Agents abandonment was made observable, while reconciliation was later rejected for lack of operational need |
| Factory-backed restart safety | Static definitions and resource builders construct fresh instances; disposed managers remain unusable |
| Runtime revision is not semantic compatibility | No semantic capability versions exist; Composer SemVer plus conformance governs compatibility |
| Pull simplification, spikes, and conformance forward | Sync/stream simplification preceded extraction; Context/Layer was rejected by executable spike; workspace and lifecycle suites appeared with second implementations |
| Name neglected ownership boundaries | CLI routing, protocol, paths, environment, configuration, discovery, tools, telemetry, and deterministic drivers each have an explicit owner |
| Require measurable user value from Cordis | Cordis was adopted only after persistent shell jobs were selected and Packagist/clean-consumer gates passed |
| Reuse existing telemetry semantics | Tell keeps `tell.event.v1` as the stable inference boundary and adds distinct `tell.resource.event.v1` resource events; PSR is an adapter |
| Protect published behavior, not every experiment | `COMPATIBILITY.md` classifies the surface against v2.8.3 and tests routing/SDK/persistence/event contracts |
| Reject Cordis-first, pinned VCS, hot observation, copied Agents models, generic container, and YAML-first SDK | Architecture tests, dependency metadata, static replacement, contract types, and the reconciliation no-go preserve every rejection |
<!-- markdownlint-enable MD013 -->

## Landing gates

The close-out task must re-run the full Tell package suite, static analysis,
repository QA relevant to touched Agents/Hub/Polyglot/Tell code, Composer
validation, Markdown and workflow validation, startup enforcement, both D11
modular examples, and the artifact clean-consumer matrix. It must then verify
Beads health, commit the complete intentional worktree, push the current
branch, and compare the remote ref to the local commit before closing the epic.
