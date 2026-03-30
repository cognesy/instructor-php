# Symfony Testing

`packages/symfony` is designed so tests can override the same container seams that production code uses.

The stable boundary is the public service graph under `src/`.
The concrete helper classes under `packages/symfony/tests/Support` are package-maintainer fixtures, not a promised runtime API for external applications.

## Recommended Testing Layers

Use the smallest layer that proves the behavior you care about:

- config and container wiring regressions:
  boot the bundle and assert normalized config, aliases, parameters, compiler-pass method calls, and service resolution
- runtime behavior inside the package:
  boot the Symfony test harness and exercise the resolved runtime services directly
- application-level integration:
  use your own `KernelTestCase` or test kernel, but override the same public contracts this package uses internally

The package tests already follow this split:

- `BundleSurfaceTest.php`, `TelemetryConfigTreeTest.php`, `AgentCtrlConfigTreeTest.php`, and `DeliveryConfigTreeTest.php` pin config-tree and bundle-wiring behavior
- `CoreBindingsTest.php`, `MessengerBindingsTest.php`, `ProgressBindingsTest.php`, `TelemetryBindingsTest.php`, and `LoggingBindingsTest.php` verify runtime resolution and framework-owned wiring
- `CoreServiceFakesTest.php` and `AdvancedRuntimeTestingHelpersTest.php` show the intended override seams for fakes and recorders

## Monorepo Harness

Inside this repository, package tests use `Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestApp`.

That harness:

- boots a minimal `FrameworkBundle` plus `InstructorSymfonyBundle`
- accepts `instructorConfig`, `frameworkConfig`, and low-level `ContainerBuilder` configurators
- cleans up kernel state and temporary cache directories automatically after each test

Typical maintainer pattern:

```php
<?php

use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestApp;

SymfonyTestApp::using(
    callback: static function (SymfonyTestApp $app): void {
        $runtime = $app->service(Cognesy\Polyglot\Inference\Inference::class);

        expect($runtime)->toBeInstanceOf(Cognesy\Polyglot\Inference\Inference::class);
    },
    instructorConfig: [
        'connections' => [
            'default' => 'openai',
            'items' => [
                'openai' => [
                    'driver' => 'openai',
                    'api_key' => 'test-key',
                    'model' => 'gpt-4o-mini',
                ],
            ],
        ],
    ],
);
// @doctest id="f415"
```

Treat `SymfonyTestApp`, `TestKernel`, and the rest of `tests/Support` as repository-local test infrastructure.
They are appropriate for package maintenance and for copying patterns, but not for downstream code to depend on directly.

## Stable Override Seams

External Symfony applications should override the same contracts and service IDs that the package itself resolves:

- core inference runtime:
  `Cognesy\Polyglot\Inference\Contracts\CanCreateInference`
- core embeddings runtime:
  `Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings`
- structured output runtime:
  `Cognesy\Instructor\Contracts\CanCreateStructuredOutput`
- event bus:
  `Cognesy\Events\Contracts\CanHandleEvents`
- native-agent session storage:
  `Cognesy\Agents\Session\Contracts\CanStoreSessions`
- telemetry exporter:
  `Cognesy\Telemetry\Domain\Contract\CanExportObservations`
- native-agent loop construction:
  `Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop`
- native-agent definitions:
  tagged `Cognesy\Agents\Template\Data\AgentDefinition` services

Those are the service seams the package itself uses when wiring production behavior, so they are the right boundary for test doubles too.

## Maintainer Fixtures In `tests/Support`

The current repository-local helpers fall into two groups.

Pattern helpers:

- `SymfonyCoreServiceOverrides`
- `SymfonyNativeAgentOverrides`
- `SymfonyTelemetryServiceOverrides`

Concrete fakes or recorders:

- `InferenceFakeRuntime`
- `EmbeddingsFakeRuntime`
- `StructuredOutputFakeRuntime`
- `ScriptedAgentLoopFactory`
- `RecordingTelemetryExporter`
- `SymfonyTestLogger`
- `MockHttpClientFactory`

The distinction matters:

- the override targets are intentionally aligned with stable public contracts
- the concrete helper classes are convenient fixtures for this monorepo, but may change as package internals evolve

If you are writing downstream application tests, copy the pattern, not the exact helper class names.

## Practical Patterns

### Replace Core Runtime Services

`CoreServiceFakesTest.php` demonstrates the baseline approach:

- replace `CanCreateInference`, `CanCreateEmbeddings`, or `CanCreateStructuredOutput`
- rebuild the corresponding façade service (`Inference`, `Embeddings`, `StructuredOutput`) from that runtime contract
- assert against recorded inputs or fake outputs

This keeps tests focused on the Symfony integration seam instead of mocking lower-level internal methods.

### Replace Native-Agent Runtime Seams

`AdvancedRuntimeTestingHelpersTest.php`, `MessengerBindingsTest.php`, and `SessionPersistenceBindingsTest.php` show the native-agent pattern:

- register one or more `AgentDefinition` services
- override `CanInstantiateAgentLoop` with a scripted loop factory
- exercise the real message handlers or session services

This is the right level for testing queue handoff, persisted sessions, and resumed executions without shelling out to real external agent processes.

### Replace Telemetry Export

Telemetry tests should override `CanExportObservations` with a recorder rather than asserting on network traffic or external exporters.

That pattern is exercised in:

- `TelemetryBindingsTest.php`
- `AdvancedRuntimeTestingHelpersTest.php`

It gives you deterministic assertions for projected observations, flush counts, and shutdown behavior across HTTP, console, and Messenger worker lifecycles.

## Public Helper Boundary

The public boundary today is:

- bundle registration
- the `instructor` config tree
- the public service contracts and service IDs resolved from `src/`

The public boundary does not currently include:

- any class under `packages/symfony/tests/Support`
- test-only fake runtime implementations
- test-only recorders, printers, registries, or harness bootstrappers

If a future release promotes reusable testing helpers into `src/`, those helpers can become part of the supported surface explicitly.
Until then, downstream users should treat the `tests/Support` classes as examples and maintenance utilities only.
