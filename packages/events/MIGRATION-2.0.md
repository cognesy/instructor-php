# Events 2.0 Migration Guide

This guide summarizes migration to the explicit Events 2.0 wiring model.

## Goals in 2.0

- Remove implicit resolver-based event bus wiring.
- Standardize on `Cognesy\Events\Contracts\CanHandleEvents`.
- Keep listener APIs explicit and consistent: `addListener(...)`, `wiretap(...)`, facade-level `onEvent(...)`.

## What Changed

1. `EventBusResolver` was removed from runtime wiring.
2. Runtime/factory paths now expect an explicit shared bus (`CanHandleEvents`) instead of resolver indirection.
3. Logging integrations no longer scan traits for auto-wiretap attachment.
4. Framework integrations wire logging explicitly to a known event bus service/binding.

## Migration Patterns

### 1) Replace resolver calls

Before:

```php
use Cognesy\Events\EventBusResolver;

$events = EventBusResolver::using($events);
```

After:

```php
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;

$events = $events ?? new EventDispatcher(name: 'app.runtime');
```

### 2) Share one bus across a runtime graph

Create one bus at the composition root and pass it to all cooperating services:

```php
use Cognesy\Events\Dispatchers\EventDispatcher;

$events = new EventDispatcher(name: 'inference.runtime');

$http = (new HttpClientBuilder(events: $events))->create();
$runtime = InferenceRuntime::using(
    preset: 'openai',
    events: $events,
    httpClient: $http,
);
```

### 3) Keep listener registration explicit

```php
$events->addListener(MyEvent::class, fn(MyEvent $event) => null, priority: 50);
$events->wiretap(fn(object $event) => null);

// Facade sugar where available:
$service->onEvent(MyEvent::class, fn(object $event) => null);
```

## Framework Integration Notes

### Laravel logging integration

- Uses explicit bus binding: `instructor-logging.event_bus_binding`
- Default: `Cognesy\Events\Contracts\CanHandleEvents::class`

### Symfony logging integration

- Uses explicit bus service id: `instructor_logging.event_bus_service`
- Default: `Cognesy\Events\Contracts\CanHandleEvents`

## Verification

Run:

```bash
composer check-events-2.0
```

This checks resolver removal, legacy union signatures, and logging trait-discovery wiring removal.
