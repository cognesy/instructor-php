# Symfony Logging Ownership

`packages/symfony` is the long-term framework entrypoint for Symfony logging.

The current codebase still carries Symfony logging pieces under `packages/logging`, but the ownership line is now:

- `packages/symfony` owns the public `instructor.logging` config subtree, Symfony bundle wiring, compiler passes, and framework-specific presets
- `packages/logging` owns reusable logging primitives such as pipelines, enrichers, filters, formatters, writers, and framework-agnostic listeners

## Existing Symfony-Specific Surface

The current Symfony logging integration lives in these files:

- `packages/logging/src/Factories/SymfonyLoggingFactory.php`
- `packages/logging/src/Integrations/Symfony/InstructorLoggingBundle.php`
- `packages/logging/src/Integrations/Symfony/DependencyInjection/InstructorLoggingExtension.php`
- `packages/logging/src/Integrations/Symfony/DependencyInjection/Configuration.php`
- `packages/logging/src/Integrations/Symfony/DependencyInjection/Compiler/WiretapEventBusPass.php`
- `packages/logging/src/Integrations/Symfony/Resources/config/services.yaml`

These files prove the implementation model, but they are no longer the target package boundary.

## Migration Strategy

### What moves behind `packages/symfony`

The following responsibilities should move into the new bundle surface:

- public config under `instructor.logging`
- service registration in `packages/symfony/resources/config/logging.yaml`
- compiler-pass ownership for wiring logging listeners into the package-owned event bus
- request, route, session, and security-aware enrichment that depends on Symfony container services
- preset selection for Symfony runtime shapes such as development, production, AgentCtrl, and later native-agent flows

### What stays in `packages/logging`

These remain reusable logging building blocks:

- `LoggingPipeline`
- enrichers
- filters
- formatters
- writers
- generic event-pipeline listeners that do not need Symfony bundle ownership

If a class depends on Symfony container services, request state, or Symfony security services, it belongs on the `packages/symfony` side of the boundary even if it composes reusable logging primitives.

## Compatibility Plan

The existing `InstructorLoggingBundle` should be treated as a compatibility layer during migration, not the future public surface.

Recommended path:

1. Implement `instructor.logging` in `packages/symfony`.
2. Keep the current `instructor_logging` bundle path temporarily as a shim.
3. Translate or proxy the old bundle behavior onto the new package-owned services where practical.
4. Mark the old Symfony logging bundle path as deprecated once the new bundle wiring is complete.
5. Remove the old bundle path only after the Symfony package is documented and stable for at least one normal release cycle.

This preserves existing adopters while making the one-box Symfony package the supported installation path.

## Current Config Surface

The bundle-owned logging subtree now lives under `instructor.logging`:

```yaml
instructor:
  logging:
    enabled: true
    preset: custom # development | production | custom
    event_bus_service: Cognesy\Events\Contracts\CanHandleEvents
    channel: instructor
    level: debug
    exclude_events: []
    include_events: []
    templates: {}
# @doctest id="75c5"
```

Current defaults:

- `enabled: false` so logging remains opt-in until the Symfony package finishes its observability presets
- `preset: production` for the safest default baseline once enabled
- `event_bus_service: Cognesy\Events\Contracts\CanHandleEvents` so logging attaches to the package-owned event bus by default
- `development` is the supported development preset name; the older `default` preset remains as a deprecated alias for one release cycle

At this stage, `packages/symfony` owns the config root, service registration, and event-bus wiretap wiring. The underlying pipeline implementation still composes reusable primitives from `packages/logging`.

## Presets

`packages/symfony` now ships three practical preset paths:

- `development`: debug-friendly logging with request and user enrichment, high-value templates for Instructor, native-agent, and AgentCtrl events, and low-value HTTP or streaming noise suppressed by default
- `production`: warning-and-above logging with noisy debug, partial-response, and streaming delta events filtered out
- `custom`: start with the reusable primitives and override `channel`, `level`, `exclude_events`, `include_events`, and `templates` directly

The older `default` preset is accepted as a compatibility alias and emits a deprecation warning so existing configs can migrate to `development` without breaking.

## Legacy Bundle Path

The older `Cognesy\Logging\Integrations\Symfony\InstructorLoggingBundle` still works as a compatibility layer, but it now emits a deprecation warning at load time. The supported installation path is:

1. install `cognesy/instructor-symfony`
2. enable `Cognesy\Instructor\Symfony\InstructorSymfonyBundle`
3. configure logging under `instructor.logging`

### Concrete Migration Steps

If your app currently enables the legacy bundle:

```php
return [
    Cognesy\Logging\Integrations\Symfony\InstructorLoggingBundle::class => ['all' => true],
];
// @doctest id="9987"
```

Replace it with:

```php
return [
    Cognesy\Instructor\Symfony\InstructorSymfonyBundle::class => ['all' => true],
];
// @doctest id="0ef4"
```

If your config currently uses the old root:

```yaml
instructor_logging:
  enabled: true
  preset: default
  event_bus_service: Cognesy\Events\Contracts\CanHandleEvents
# @doctest id="80f9"
```

Move it under the package root:

```yaml
instructor:
  logging:
    enabled: true
    preset: development
    event_bus_service: Cognesy\Events\Contracts\CanHandleEvents
# @doctest id="92ee"
```

Migration notes:

- move from `instructor_logging` to `instructor.logging`
- rename the old `default` preset to `development`
- keep using `event_bus_service` only if you intentionally wire logging to a non-default package-owned bus
- keep custom `exclude_events`, `include_events`, and `templates` arrays; the new bundle still passes those through to the reusable logging pipeline primitives

## Implementation Guidance

When later tasks wire logging for real:

- prefer `instructor.logging` over a second config root
- reuse `packages/logging` primitives instead of copying logic
- keep event-bus attachment aligned with the package-owned `CanHandleEvents` service from `packages/symfony`
- avoid leaving request or security enrichment factories in `packages/logging` once the Symfony package owns the bundle surface

The intended end state is:

- Symfony apps install `cognesy/instructor-symfony`
- Symfony logging is configured through the same `instructor` root as the rest of the package
- `packages/logging` remains reusable outside Symfony without owning a second framework bundle long term
