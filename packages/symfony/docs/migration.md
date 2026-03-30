# Symfony Migration Guide

This guide is for Symfony applications that already use scattered InstructorPHP glue and want to move onto the supported `packages/symfony` bundle surface.

The main migration goal is simple:

- keep domain logic in the source packages
- move Symfony-specific registration, config, and framework defaults under `cognesy/instructor-symfony`

## When You Should Migrate

You are a migration candidate if your app currently does any of the following:

- registers its own `Cognesy\Events\Dispatchers\SymfonyEventDispatcher` bridge or event-bus parent wiring
- enables `Cognesy\Logging\Integrations\Symfony\InstructorLoggingBundle`
- keeps framework-specific logging, telemetry, or Messenger wiring in `services.yaml`
- wires native-agent persistence or delivery helpers directly in app code
- mixes `packages/events`, `packages/logging`, and app-local container glue to reproduce a framework package manually

## What Moves, What Stays

Moves under `packages/symfony`:

- bundle registration
- the public `instructor` config root
- Symfony-aware HTTP transport defaults
- framework event mirroring
- package-owned delivery defaults
- telemetry exporter wiring and lifecycle cleanup
- logging presets and bundle-facing compiler-pass wiring

Stays in source packages:

- reusable event primitives in `packages/events`
- reusable logging primitives in `packages/logging`
- runtime logic in `packages/instructor`, `packages/polyglot`, `packages/agents`, and `packages/agent-ctrl`

The migration is about ownership, not duplication.
You should end up with less app-local Symfony glue, not more wrappers around the same low-level classes.

## Step 1. Install And Register The Bundle

```bash
composer require cognesy/instructor-symfony
```

Then enable the bundle:

```php
<?php

return [
    Cognesy\Instructor\Symfony\InstructorSymfonyBundle::class => ['all' => true],
];
```

If you still have the legacy logging bundle registered, remove it from `config/bundles.php`.

## Step 2. Consolidate Config Under `instructor`

The supported config root is now:

```yaml
instructor:
```

Move Symfony-related app config under these subtrees as needed:

- `connections`
- `embeddings`
- `extraction`
- `http`
- `events`
- `agent_ctrl`
- `sessions`
- `telemetry`
- `logging`
- `delivery`

Do not keep a second Symfony-specific root for logging or event delivery once the new bundle is in place.

## Step 3. Replace Manual Event-Bus Bridging

Before:

- app code constructs or registers `SymfonyEventDispatcher`
- app code decides how `CanHandleEvents` is parented or mirrored

After:

- let `packages/symfony` own `CanHandleEvents`
- configure bridge behavior through:

```yaml
instructor:
  events:
    dispatch_to_symfony: true
```

Important boundary:

- `packages/events` still owns the reusable `SymfonyEventDispatcher` primitive
- `packages/symfony` owns when and how that primitive is registered in a Symfony application

So keep `packages/events` for reusable code, but stop wiring the bridge manually in application service definitions unless you are deliberately replacing the package default.

## Step 4. Migrate Logging

Before:

```php
<?php

return [
    Cognesy\Logging\Integrations\Symfony\InstructorLoggingBundle::class => ['all' => true],
];
```

```yaml
instructor_logging:
  enabled: true
  preset: default
```

After:

```php
<?php

return [
    Cognesy\Instructor\Symfony\InstructorSymfonyBundle::class => ['all' => true],
];
```

```yaml
instructor:
  logging:
    enabled: true
    preset: development
```

Migration notes:

- move from `instructor_logging` to `instructor.logging`
- rename the old `default` preset to `development`
- keep custom `include_events`, `exclude_events`, and `templates` values; the new path still forwards those into the reusable logging pipeline
- the legacy logging bundle should be treated as a compatibility shim, not the long-term public surface

## Step 5. Move Telemetry Wiring Into The Package

If your app currently builds exporter services, projectors, or lifecycle listeners by hand, move that config under:

```yaml
instructor:
  telemetry:
    enabled: true
    driver: otel
```

The package now owns:

- exporter selection
- projector composition
- runtime bridge wiring
- HTTP, console, and Messenger cleanup hooks

Application code should still own:

- credentials and endpoint values
- any domain-specific post-processing or alerting after export

## Step 6. Use Package Delivery Seams Instead Of Ad Hoc Messaging

If your app currently forwards runtime events or execution handoff through custom Messenger messages, move toward the package-owned seams:

- `ExecuteAgentCtrlPromptMessage`
- `ExecuteNativeAgentPromptMessage`
- `RuntimeObservationMessage`

Configure the observation bridge explicitly:

```yaml
instructor:
  delivery:
    messenger:
      enabled: true
      bus_service: message_bus
      observe_events:
        - Cognesy\Agents\Events\AgentExecutionCompleted
```

This makes the ownership split clear:

- execution and observation handoff live under `packages/symfony`
- low-level runtime events still come from the source packages
- your application still decides which forwarded events deserve queue fan-out

## Step 7. Replace App-Local Session Defaults Only Where Needed

If your app currently chooses native-agent persistence through local service definitions, start with the package-owned config instead:

```yaml
instructor:
  sessions:
    store: file
    file:
      directory: '%kernel.cache_dir%/instructor/agent-sessions'
```

Only replace `Cognesy\Agents\Session\Contracts\CanStoreSessions` directly if you truly need a custom backend.

## Rollout Checklist

- install `cognesy/instructor-symfony`
- enable `InstructorSymfonyBundle`
- consolidate config under `instructor`
- remove legacy logging bundle registration
- stop manually wiring the event bridge unless intentionally overriding package defaults
- move telemetry and delivery config into `instructor.telemetry` and `instructor.delivery`
- run your package or app test suite with both HTTP and Messenger contexts covered

## Current Compatibility Notes

The migration path is already supported for:

- event-bus ownership under `CanHandleEvents`
- telemetry exporter and lifecycle wiring
- logging preset and bundle-root migration
- AgentCtrl runtime and Messenger handoff surfaces
- native-agent session store selection

Still evolving:

- split-package publication bootstrap and Packagist registration for `cognesy/instructor-symfony`
- broader docs-site discoverability outside the package folder

Those rollout items do not change the runtime ownership model described above.
