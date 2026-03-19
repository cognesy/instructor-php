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
The current baseline already includes the bundle surface, config translation, and initial container bindings for core runtime services.
Later tasks will expand event wiring, transport specialization, AgentCtrl, native agents, persistence, observability, testing, and the migration path.

## Planned Surface

- bundle entrypoint under `Cognesy\Instructor\Symfony\`
- one public `instructor` config root with explicit subtrees
- framework-owned integration for core runtime services, agents, observability, and testing

## Current Container Entry Points

The package now registers the initial core contracts and developer-facing services:

- `Cognesy\Config\Contracts\CanProvideConfig`
- `Cognesy\Http\Contracts\CanSendHttpRequests`
- `Cognesy\Polyglot\Inference\Contracts\CanCreateInference`
- `Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings`
- `Cognesy\Instructor\Contracts\CanCreateStructuredOutput`
- `Cognesy\Polyglot\Inference\Inference`
- `Cognesy\Polyglot\Embeddings\Embeddings`
- `Cognesy\Instructor\StructuredOutput`

Ownership boundaries:

- `packages/symfony` owns Symfony bundle registration, config normalization, service wiring, and framework defaults
- `packages/events` continues to own reusable event-dispatch primitives such as the raw Symfony bridge
- `packages/logging` continues to own reusable logging primitives and factories while `packages/symfony` becomes the framework package surface

## Documentation

- `packages/symfony/docs/overview.md`
- `packages/symfony/docs/configuration.md`
- `packages/symfony/docs/quickstart.md`
- `packages/symfony/CHEATSHEET.md`

## Distribution Readiness

The split-workflow matrix is now generated with `packages/symfony` included.

Remaining bootstrap steps outside this package task:

1. Create the split repository `cognesy/instructor-symfony`.
2. Let the split workflow populate the repository, or bootstrap it manually if publication is needed immediately.
3. Verify the split repo `main` branch contains at least `composer.json`, `src/`, and `README.md`.
4. Submit the split repository to Packagist.
5. Verify Packagist metadata and package resolution endpoints after submission.
