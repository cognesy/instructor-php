# Kernel and contracts

## Minimal static kernel

The first kernel is a composition root, not a lifecycle broker. If code
mentions prompts, models, branches, Symfony commands, canonical records,
filesystem paths, rendering formats, polling, or dependency restarts, it does
not belong in the kernel.

Its complete responsibility set is:

- admit factory-backed module definitions;
- reject duplicate module IDs and singleton capability providers;
- report all missing mandatory capabilities in one pass;
- construct an immutable standard or application-selected profile;
- expose only purpose-built Tell facades; and
- dispose constructed resources in deterministic reverse order.

Replacement is boot-time composition. Runtime reconciliation is absent until
a separate resource host justifies it.

## Contract identity and dependencies

Singleton capabilities use interface FQCNs as identity. A provider must return
an object implementing the advertised interface. Raw keys such as `workspace`
or `tools` are not public capability identities.

Ordered contributions use an explicit aggregator contract. Duplicate command
names, tool names, or singleton providers fail admission instead of relying on
last-writer-wins behavior.

Initial Tell contracts may use stable public `AgentState`, `ExecutionStatus`,
`InferenceUsage`, cancellation, and clock types from Agents and Polyglot. That
is more honest and compatible than creating parallel boundary values. A future
decoupling can be proposed and versioned separately.

## Factory-backed definitions

Definitions describe how to create an implementation; they do not store a
reusable live instance.

```php
$composition->with(new TellModuleDefinition(
    id: 'workspace.filesystem',
    requires: [CanResolveTellPaths::class],
    provides: [
        CanManageTellWorkspace::class,
        CanAccessTellConversations::class,
        CanReadTellBranchConfiguration::class,
    ],
    factory: fn (TellDependencies $dependencies): TellModule =>
        new FilesystemWorkspaceModule(
            $dependencies->require(CanResolveTellPaths::class),
        ),
));
```

Only composition code may read declared dependencies. Product code receives
constructor-injected interfaces and never retains a service locator.

Factories are mandatory because a later restartable resource host must build
a fresh implementation after disposal. Reapplying the same stateful object is
not restart semantics.

## Composition mechanism spike

The existing `Utils\Context`, `Layer`, and `Key` types already provide tested,
typed, immutable composition. They are a credible implementation candidate,
but they have no current product usage, have no lifecycle, and use right-biased
merge semantics that could hide duplicate providers.

Step 3 must compare two executable options:

- ordinary named factories plus explicit graph validation; and
- a narrow Tell adapter over `Utils\Context` and `Layer` with duplicate checks.

Choose the smaller public model with clearer diagnostics. Do not create a new
general container, and do not adopt Context/Layer merely because it exists.

## Required profile capabilities

The SDK profile requires execution, workspace, conversations, configuration,
agent construction, paths, and observation. The CLI profile additionally
requires application assembly and one-run protocol adaptation.

Optional catalogue, direct-tool, or legacy-session capability absence produces
a typed unsupported-capability result at the relevant facade. It does not
encourage arbitrary public lookup.

## Where Cordis fits

Cordis PHP is the preferred runtime when Tell owns processes, clients,
listeners, or other scoped resources across requests. It already implements
and tests scoped cleanup, dependency restart, validation, health, isolation,
interception, and reconciliation.

Tell must not copy those mechanics. It also must not depend on Cordis before a
production release is normally Composer-installable. A local path repository
may prove an adapter in a disposable spike, but shipped code requires a tagged
Packagist-resolvable release and clean-consumer verification.

Cordis binding revisions describe restart identity. They are not semantic
versions of capability contracts. Interface compatibility follows Composer
semantic versioning and executable conformance suites.
