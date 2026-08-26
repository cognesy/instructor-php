# Tell modular redesign

This research defines a capability-oriented redesign of Tell. The immediate
target is a small static composition boundary, PHP interfaces as capability
contracts, and concrete modules selected by an external composition root.

Cordis remains the preferred lifecycle runtime for resource-owning, long-lived
hosts. It is deliberately not the foundation of the first migration phase.
Static dependency inversion and dynamic lifecycle are separate problems, and
Tell should earn the latter with a user-visible feature such as MCP lifecycle
or persistent shell jobs.

The proposal is based on the current Tell source, the current Cordis PHP
implementation, and the D11 Tell Harness. It is a migration design, not a
claim that Tell is modular already.

## Recommended architecture

```text
application, framework, or CLI composition root
                    |
                    v
          TellHost and standard profiles
                    |
                    v
       static composition and graph admission
                    |
                    v
          PHP capability contracts
                    |
                    v
 agent | workspace | provider | tools | observation | CLI modules

optional resource host
                    |
                    v
        Cordis-backed scoped resources
```

The first `tell-kernel` is intentionally small: validate a selected capability
graph, build modules from factories, expose standard profiles, and dispose the
booted host. It has no reconciliation, dependency restart, or dynamic service
registry.

The `cognesy/instructor-tell` distribution remains the convenient SDK and CLI.
Applications can replace modules before boot without learning Cordis. The
opt-in shell-job resource host uses Cordis internally without changing the
ordinary SDK programming model. Live supervised reconciliation is currently a
documented no-go, not an implied next layer.

## Documents

Concept dimensions:

- [Current state](concept/current-state.md)
- [Architecture boundaries](concept/architecture-boundaries.md)
- [Kernel and contracts](concept/kernel-and-contracts.md)
- [Capability catalogue](concept/capability-catalogue.md)
- [Modules and wiring](concept/modules-and-wiring.md)
- [Lifecycle and state](concept/lifecycle-and-state.md)
- [Persistent shell jobs](concept/persistent-shell-jobs.md)
- [Configuration, discovery, and security](concept/configuration-discovery-and-security.md)
- [Developer experience](concept/developer-experience.md)
- [Compatibility, testing, and operations](concept/compatibility-testing-and-operations.md)
- [Decisions and non-goals](concept/decisions-and-non-goals.md)
- [Team feedback assessment](feedback-assessment.md)
- [Scoped lifecycle conformance](scoped-lifecycle-conformance.md)
- [Supervised reconciliation decision](reconciliation-decision.md)
- [Completion matrix](completion-matrix.md)

Ordered delivery plan:

- [Step 1: Freeze behavior and baseline](plan/01-freeze-behavior-and-baseline.md)
- [Step 2: Simplify runtime and global state](plan/02-simplify-runtime-and-global-state.md)
- [Step 3: Extract contracts and static composition](plan/03-extract-contracts-and-static-composition.md)
- [Step 4: Modularize agent and provider runtime](plan/04-modularize-agent-and-provider-runtime.md)
- [Step 5: Modularize workspace and conversations](plan/05-modularize-workspace-and-conversations.md)
- [Step 6: Modularize configuration, tools, and observation](plan/06-modularize-configuration-tools-and-observation.md)
- [Step 7: Migrate CLI, protocol, and SDK wiring](plan/07-migrate-cli-protocol-and-sdk-wiring.md)
- [Step 8: Prove replacement and package boundaries](plan/08-prove-replacement-and-package-boundaries.md)
- [Step 9: Deliver scoped-resource lifecycle](plan/09-deliver-scoped-resource-lifecycle.md)
- [Step 10: Supervised reconciliation (conditional no-go)](plan/10-enable-supervised-reconciliation.md)

## Baseline decisions

Tell retains PHP `^8.3`. The published `cognesy/instructor-tell` package and
the main package dependencies already support that floor, and current Cordis
PHP now supports `^8.2`. There is no justified PHP 8.4 requirement.

Cordis PHP `v1.0.1` is tagged, released, and normally resolvable from Packagist.
Its supported PHP/Symfony matrix and relevant cleanup/restart behavior are
tested, and a clean PHP `^8.3` consumer installs it without a path repository.
The complete evidence is recorded in
[Cordis production dependency gate](cordis-production-gate.md).
