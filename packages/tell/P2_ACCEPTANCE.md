# Tell P2 acceptance audit

This document records the current-source evidence for the P2 modularization
hard cut. The governing requirements are the research plan's acceptance rules;
this audit maps them to executable repository evidence.

## Architecture completion matrix

<!-- markdownlint-disable MD013 -->

| Area | Result | Current evidence |
| --- | --- | --- |
| Core and Data | Complete | `BoundaryDependencyTest` enforces Data-only dependencies, Core-to-Data/Core direction, pure contracts, and no framework containers. `TellRuntime` consumes workspace, configuration, observation, tracing, agent, and cancellation contracts. |
| Capabilities | Complete | Replaceable implementations live under `Capability/<Capability>/<Strategy>`. Token-based architecture rules reject sibling-provider imports, import side effects, external provider selection, and provider-owned composition. The rules resolve aliases, grouped imports, fully qualified references, constructors, and static factories; deliberately invalid fixtures cover each form while a provider-private helper remains valid. |
| Composition | Complete | `Composition/Standalone/Profile/StandardTellModules.php` explicitly selects every standard implementation. `Composition/Standalone/Host` owns graph validation and lifecycle. The separately owned, opt-in shell-job host and its defaults live under `Composition/Standalone/Profile/ShellJob`; no host API or default selection remains in its capability namespace. `TellHostTest` covers cardinality, requirements, replacement, cycles, factory freshness, reverse exhaustive disposal, and the absence of a service locator. |
| Adapters | Complete | Console and one-run protocol code live under `Adapter`. Workspace commands delegate through focused inspection, maintenance, and session contracts backed by Core facades; adapters no longer orchestrate raw workspace context, branch stores/resolvers, conversation readers, or compaction runners. The adapter rule permits Data, Core contracts, and an explicit small set of public Core boundary values only. Invalid fixtures cover `BranchResolver`, `BranchStore`, `ConversationReader`, and `CompactionRunner`, while command behavior tests exercise the same Core operations through Symfony Console. |
| Workspace | Complete | Core owns branch, conversation, session, execution, and Arena semantics. Filesystem and memory providers implement `CanProvideTellWorkspace`, including branch-configuration reads, and share `WorkspaceModuleConformanceTest` and `ArenaBackendConformanceTest`. `StandardHostRuntimeTest` replaces the provider before boot, then proves a durable headless run plus public conversation, branch, and configuration operations and the CLI `init`, `branch`, and `config` paths entirely in memory. Generic profile helpers accept the contract rather than `WorkspaceRepository`; backend-neutral execution and conversation services have neutral Core ownership and module names. |
| Compatibility and public surface | Complete | `Tell.php` is the only root facade. The source root is closed to Adapter, Capability, Composition, Core, Data, and Testing. PSR-4 reflection checks, removed-API assertions, and searches for former namespaces prove the hard cut without aliases or forwarding classes. |
| Framework readiness | Complete for P2 | `TellHostConformance` receives a test-only callback harness for boot, `CanRunTell` access, disposal, and disposed-access behavior; neither its contract nor harness imports a standalone host type. `HostCompositionConformanceTest` runs those laws against the standalone host. The runtime accepts constructed contracts rather than a container, has no console dependency, and exposes explicit worker/profile and per-run lifetimes. Symfony and Laravel packages remain intentionally out of scope. |

<!-- markdownlint-enable MD013 -->

## Package-boundary decision

All capability families remain internal modules of `cognesy/instructor-tell`.
There is no current evidence for a separate Composer package:

- no capability introduces an optional heavy dependency that the Tell package
  can avoid by extraction;
- no capability has an independent external consumer or release cadence;
- no capability requires a security or process-isolation boundary;
- ownership is not split across teams or repositories;
- no provider is maintained outside this repository.

The existing package boundary is therefore the smallest justified boundary.
Future `tell-symfony` or `tell-laravel` integration packages should be created
only for a concrete host application and must run the shared host conformance
suite. Capability-per-package extraction is not part of this design.

## Verification record

The final audit uses these authoritative gates from the repository root:

```bash
composer --working-dir=packages/tell tests
composer --working-dir=packages/tell phpstan
composer --working-dir=packages/tell psalm
(cd packages/tell && vendor/bin/composer-require-checker check composer.json)
(cd packages/tell && vendor/bin/composer-unused composer.json --no-progress)
composer --working-dir=packages/tell dump-autoload --optimize --strict-psr --no-dev
php packages/tell/scripts/benchmark-startup.php --iterations=10 --enforce
bash packages/tell/scripts/clean-consumer.sh
composer validate --strict packages/tell/composer.json
composer qa
composer qa:docs-sites
git diff --check
bd doctor --agent --json
```

The package suite passes 397 tests with 2,987 assertions. Package PHPStan and
Psalm report no errors, as do the complete root QA gate's PHPStan, Psalm,
formatting, self-knowledge, and Semgrep checks. Composer validation, direct and
unused dependency checks, and the production PSR-4 scan pass. The clean
consumer matrix installs and runs PHP 8.3, 8.4, and 8.5 with both lowest and
highest dependencies and no path repositories.

The enforced ten-run startup benchmark passes with an 82.517 ms median and
85.188 ms p95 for `--version` against a 250 ms budget, and an 83.810 ms median
and 85.143 ms p95 for the home screen against a 500 ms budget. All measured
filesystem scan counts exactly match their declared budgets. The production
documentation build and diff whitespace checks pass. Beads doctor reports all
78 checks passing; its warnings are limited to the inactive optional federation
server and the expected uncommitted worktree for this implementation session.
Every D11 Tell harness snippet passes PHP syntax validation. The modular-host
replacement example and persistent shell-job lifecycle example also execute
successfully against the hard-cut namespaces.
