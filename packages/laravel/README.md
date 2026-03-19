# Laravel Package

Laravel integration for InstructorPHP.

It provides:
- Laravel service provider and config
- Facades for `StructuredOutput`, `Inference`, `Embeddings`, and `AgentCtrl`
- Laravel-specific HTTP client and HTTP pool drivers
- a Laravel-bound `CanSendHttpRequests` transport implementation
- native `Cognesy\Agents` container bindings, registry loading, and session runtime
- database-backed native agent sessions, broadcasting helpers, telemetry wiring, and logging presets
- testing fakes for facade-based tests and Laravel-native helper utilities for native agents
- Artisan commands for install, smoke-test, and response-model scaffolding

The package now treats native `Cognesy\\Agents` runtime configuration and `AgentCtrl`
code-agent execution as separate Laravel surfaces:
- `agents` is reserved for native agent runtime integration
- `agent_ctrl` configures CLI code agents exposed through the `AgentCtrl` facade
- `telemetry` is the first-class config namespace for Laravel telemetry wiring

## Example

```php
<?php

use App\ResponseModels\PersonData;
use Cognesy\Instructor\Laravel\Facades\StructuredOutput;

$person = StructuredOutput::with(
    messages: 'John Smith is 30 years old',
    responseModel: PersonData::class,
)->get();
```

## Documentation

- `packages/laravel/docs/index.md`
- `packages/laravel/CHEATSHEET.md`
