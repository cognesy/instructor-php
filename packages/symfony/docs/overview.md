# Symfony Overview

`packages/symfony` is the first-party Symfony integration package for InstructorPHP.

The package is still landing in phases, but the baseline framework surface is already live:

- one bundle to register
- one coherent `instructor` config root
- core runtime bindings for Inference, Embeddings, StructuredOutput, HTTP, and events
- initial AgentCtrl builders and context-aware runtime adapters

The package consolidates Symfony-facing glue that would otherwise be scattered across application code or lower-level packages.
The intent is one supported installation path for Symfony apps instead of ad hoc event, HTTP, and container wiring.

The public framework config root remains `instructor`, with explicit subtrees for:

- core runtime configuration
- AgentCtrl
- native agents
- sessions
- telemetry
- logging
- testing
- delivery

Framework ownership is split intentionally:

- `packages/symfony` owns Symfony-specific registration, config, and service wiring
- `packages/events` keeps reusable event-dispatch primitives
- `packages/logging` keeps reusable logging primitives while the Symfony package remains the framework-facing entrypoint

Use the quickstart for installation and first-working examples, then the configuration guide for the full supported config surface.
