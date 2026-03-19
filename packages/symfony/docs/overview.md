# Symfony Overview

`packages/symfony` is the planned batteries-included Symfony integration package for InstructorPHP.

The package will consolidate first-party Symfony glue that is currently scattered across other packages, especially event and logging integrations, into one supported framework package.

The target outcome is a Symfony-native installation path that gives applications:

- one bundle to register
- one coherent config root
- container bindings for core runtime services
- native integrations for agents, observability, and testing

The public framework config root is reserved as `instructor`, with explicit subtrees for core runtime, agents, AgentCtrl, sessions, telemetry, logging, testing, and delivery concerns.

Framework ownership is split intentionally:

- `packages/symfony` owns Symfony-specific registration, config, and service wiring
- `packages/events` keeps reusable event-dispatch primitives
- `packages/logging` keeps reusable logging primitives while the Symfony package becomes the framework-facing entrypoint

This file is intentionally minimal until the bundle, config tree, and service wiring are implemented.
