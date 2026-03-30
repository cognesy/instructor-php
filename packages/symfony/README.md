# Symfony Package

Batteries-included Symfony integration for InstructorPHP.

It is intended to become the first-party framework package for Symfony applications using:

- Instructor primitives
- Polyglot inference and embeddings
- native `Cognesy\Agents`
- `AgentCtrl`
- shared events, logging, and telemetry wiring
- testing helpers and Symfony-native bundle integration

The package is being introduced in phases.
The current baseline already includes the bundle surface, config translation, initial container bindings for core runtime services, the first AgentCtrl container/runtime adapters, native-agent registry/session wiring, and the initial Messenger delivery seam.
The current baseline also includes an explicit session-persistence seam under `instructor.sessions`, with built-in in-memory and file-backed storage paths for native agents.
Telemetry is now package-owned too: explicit exporter selection, projector composition, shared event-bus bridge wiring, and lifecycle cleanup for HTTP, console, and Messenger worker contexts all live under `instructor.telemetry`.
Later tasks will expand broader testing and the migration path.

## Planned Surface

- bundle entrypoint under `Cognesy\Instructor\Symfony\`
- one public `instructor` config root with explicit subtrees
- framework-owned integration for core runtime services, agents, observability, and testing

## Current Container Entry Points

The package now registers the initial core contracts and developer-facing services:

- `Cognesy\Config\Contracts\CanProvideConfig`
- `Cognesy\Http\Contracts\CanSendHttpRequests`
- `Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrl`
- `Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrlRuntimes`
- `Cognesy\Polyglot\Inference\Contracts\CanCreateInference`
- `Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings`
- `Cognesy\Instructor\Contracts\CanCreateStructuredOutput`
- `Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteAgentCtrlPromptMessageHandler`
- `Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteNativeAgentPromptMessageHandler`
- `Cognesy\Polyglot\Inference\Inference`
- `Cognesy\Polyglot\Embeddings\Embeddings`
- `Cognesy\Instructor\StructuredOutput`

The AgentCtrl runtime layer now includes context-aware `cli`, `http`, and `messenger` adapters plus typed continuation and handoff references.
The package also exposes explicit Messenger message and handler seams for queued AgentCtrl prompts, queued native-agent prompts, and opt-in runtime observation forwarding.
Native agent tools, capabilities, `AgentDefinition` services, and `SchemaRegistration` services now autoconfigure into the package-owned registries, while manual `AgentRegistryTags::*` tags remain available for explicit overrides.

Ownership boundaries:

- `packages/symfony` owns Symfony bundle registration, config normalization, service wiring, and framework defaults
- `packages/events` continues to own reusable event-dispatch primitives such as the raw Symfony bridge
- `packages/logging` continues to own reusable logging primitives, while the Symfony-facing logging bundle path is planned to migrate behind `packages/symfony`

## Documentation

- `packages/symfony/docs/overview.md`
- `packages/symfony/docs/configuration.md`
- `packages/symfony/docs/runtime-surfaces.md`
- `packages/symfony/docs/sessions.md`
- `packages/symfony/docs/testing.md`
- `packages/symfony/docs/telemetry.md`
- `packages/symfony/docs/quickstart.md`
- `packages/symfony/docs/logging.md`
- `packages/symfony/docs/delivery.md`
- `packages/symfony/docs/operations.md`
- `packages/symfony/docs/migration.md`
- `packages/symfony/CHEATSHEET.md`

## Distribution Readiness

The split-workflow matrix is now generated with `packages/symfony` included.

Remaining bootstrap steps outside this package task:

1. Create the split repository `cognesy/instructor-symfony`.
2. Let the split workflow populate the repository, or bootstrap it manually if publication is needed immediately.
3. Verify the split repo `main` branch contains at least `composer.json`, `src/`, and `README.md`.
4. Submit the split repository to Packagist.
5. Verify Packagist metadata and package resolution endpoints after submission.
