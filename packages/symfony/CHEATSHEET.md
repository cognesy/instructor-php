---
title: Symfony
description: Symfony integration — bundle, config root, container wiring, event bridge, and HTTP transport
package: symfony
---

# Symfony Package Cheatsheet

Code-verified reference for `packages/symfony`.

## Package Wiring

Composer package:
- `cognesy/instructor-symfony`

Namespace root:
- `Cognesy\Instructor\Symfony\`

Bundle entrypoint:
- `Cognesy\Instructor\Symfony\InstructorSymfonyBundle`

Bundle extension alias:
- `instructor`

Service definitions loaded by `InstructorSymfonyExtension`:
- `packages/symfony/resources/config/core.yaml`
- `packages/symfony/resources/config/polyglot.yaml`
- `packages/symfony/resources/config/events.yaml`
- `packages/symfony/resources/config/delivery.yaml`
- `packages/symfony/resources/config/agent_ctrl.yaml`
- `packages/symfony/resources/config/agents.yaml`
- `packages/symfony/resources/config/sessions.yaml`
- `packages/symfony/resources/config/telemetry.yaml`
- `packages/symfony/resources/config/logging.yaml`
- `packages/symfony/resources/config/testing.yaml`
- `packages/symfony/resources/config/messenger.yaml`

## Config Root

Public Symfony config root:
- `instructor`

Top-level keys accepted by `Configuration`:
- `connections`
- `embeddings`
- `extraction`
- `http`
- `events`
- `agent_ctrl`
- `agents`
- `sessions`
- `telemetry`
- `logging`
- `testing`
- `delivery`

## Config Translation

`SymfonyConfigProvider` is the framework-facing config adapter and container binding for:
- `Cognesy\Config\Contracts\CanProvideConfig`

Typed config objects created from Symfony config:
- `Cognesy\Polyglot\Inference\Config\LLMConfig`
- `Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig`
- `Cognesy\Instructor\Config\StructuredOutputConfig`
- `Cognesy\Http\Config\HttpClientConfig`

Useful provider methods:
- `llm(string $connection = ''): LLMConfig`
- `embeddings(string $connection = ''): EmbeddingsConfig`
- `structuredOutput(): StructuredOutputConfig`
- `httpClient(): HttpClientConfig`
- `get(string $path, mixed $default = null): mixed`
- `has(string $path): bool`

Supported lookup shapes:
- `instructor.connections.default` plus named connections under `items` or `connections`
- flat `instructor.connections.<name>` connection maps
- legacy `default` fallback for LLM default selection

## Container Surface

Public aliases and services available today:
- `Cognesy\Config\Contracts\CanProvideConfig`
- `Cognesy\Events\Contracts\CanHandleEvents`
- `Cognesy\Http\HttpClient`
- `Cognesy\Http\Contracts\CanSendHttpRequests`
- `Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrl`
- `Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrlRuntimes`
- `Cognesy\Polyglot\Inference\Contracts\CanCreateInference`
- `Cognesy\Polyglot\Inference\Inference`
- `Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings`
- `Cognesy\Polyglot\Embeddings\Embeddings`
- `Cognesy\Instructor\Contracts\CanCreateStructuredOutput`
- `Cognesy\Instructor\StructuredOutput`

Registered support services:
- `Cognesy\AgentCtrl\Builder\ClaudeCodeBridgeBuilder`
- `Cognesy\AgentCtrl\Builder\CodexBridgeBuilder`
- `Cognesy\AgentCtrl\Builder\OpenCodeBridgeBuilder`
- `Cognesy\AgentCtrl\Builder\PiBridgeBuilder`
- `Cognesy\AgentCtrl\Builder\GeminiBridgeBuilder`
- `Cognesy\Instructor\Symfony\Support\SymfonyConfigProvider`
- `Cognesy\Instructor\Symfony\Support\SymfonyEventBusFactory`
- `Cognesy\Instructor\Symfony\Support\SymfonyHttpTransportFactory`
- `Cognesy\Events\Dispatchers\SymfonyEventDispatcher`
- `Cognesy\Events\Dispatchers\EventDispatcher`
- `Cognesy\Polyglot\Inference\InferenceRuntime`
- `Cognesy\Polyglot\Embeddings\EmbeddingsRuntime`
- `Cognesy\Instructor\StructuredOutputRuntime`
- `Cognesy\Instructor\Symfony\Delivery\Progress\ProgressEventDispatcher`
- `Cognesy\Instructor\Symfony\Delivery\Progress\Contracts\CanHandleProgressUpdates`
- `Cognesy\Instructor\Symfony\Delivery\Cli\SymfonyCliObservationFormatter`
- `Cognesy\Instructor\Symfony\Delivery\Cli\SymfonyCliObservationPrinter`
- `Cognesy\Telemetry\Application\Telemetry`
- `Cognesy\Telemetry\Domain\Contract\CanExportObservations`
- `Cognesy\Instructor\Symfony\Telemetry\NullTelemetryExporter`
- `Cognesy\Instructor\Symfony\Support\SymfonyLoggingFactory`

## Event Wiring

The bundle owns `CanHandleEvents` through a package-local event bus:
- `Cognesy\Events\Contracts\CanHandleEvents` -> `Cognesy\Events\Dispatchers\EventDispatcher`

Bridge behavior:
- the package creates a `Cognesy\Events\Dispatchers\SymfonyEventDispatcher` bridge around Symfony's `event_dispatcher` when available
- the package event bus uses that bridge as its parent when `instructor.events.dispatch_to_symfony` is truthy
- legacy `instructor.events.bridge_to_symfony` is still honored as a fallback key
- if neither key is set, bridging defaults to `true`

## HTTP Transport

The bundle owns `CanSendHttpRequests` through `Cognesy\Http\HttpClient`.

Transport rules implemented by `SymfonyHttpTransportFactory`:
- `instructor.http.driver: symfony` uses Symfony's `http_client` service when it is available
- `instructor.http.driver: framework` and `instructor.http.driver: http_client` are normalized to `symfony`
- if the Symfony client service is missing, or the configured driver is not `symfony`, the package falls back to the standard `HttpClientBuilder` driver resolution path

## Runtime Services

AgentCtrl:
- `Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrl` is the package-owned container entrypoint for CLI code-agent builders
- `Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrlRuntimes` is the package-owned registry for context-specific AgentCtrl execution adapters
- backend builder services are public and non-shared for `ClaudeCodeBridgeBuilder`, `CodexBridgeBuilder`, `OpenCodeBridgeBuilder`, `PiBridgeBuilder`, and `GeminiBridgeBuilder`
- named service IDs exist for `instructor.agent_ctrl`, `instructor.agent_ctrl.builder.default`, and `instructor.agent_ctrl.builder.<backend>`
- backend builders inherit shared defaults from `instructor.agent_ctrl.defaults` and per-backend overrides from `instructor.agent_ctrl.backends.<backend>`
- builder access is guarded by `instructor.agent_ctrl.enabled`
- runtime service IDs exist for `instructor.agent_ctrl.runtimes`, `instructor.agent_ctrl.runtime.cli`, `instructor.agent_ctrl.runtime.http`, and `instructor.agent_ctrl.runtime.messenger`
- runtime adapters expose explicit policy for `cli`, `http`, and `messenger` entrypoints so Symfony applications can check whether inline execution is allowed or should be handed off to Messenger
- runtime adapters also expose continuation seams through `continuationPolicy()`, `continuation()`, `continueLast()`, `resumeSession()`, and `handoff(AgentResponse)`
- `continue_last` references are same-context only; use `handoff()` / `resumeSession()` when handing work from HTTP or CLI into Messenger

Inference:
- `Cognesy\Polyglot\Inference\InferenceRuntime` is created from `LLMConfig`, `CanHandleEvents`, and `CanSendHttpRequests`
- `Cognesy\Polyglot\Inference\Contracts\CanCreateInference` aliases that runtime
- `Cognesy\Polyglot\Inference\Inference` is registered as a public service

Embeddings:
- `Cognesy\Polyglot\Embeddings\EmbeddingsRuntime` is created from `EmbeddingsConfig`, `CanHandleEvents`, and `CanSendHttpRequests`
- `Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings` aliases that runtime
- `Cognesy\Polyglot\Embeddings\Embeddings` is registered as a public service

Structured output:
- `Cognesy\Instructor\StructuredOutputRuntime` is created from `LLMConfig`, `StructuredOutputConfig`, `CanHandleEvents`, and `CanSendHttpRequests`
- `Cognesy\Instructor\Contracts\CanCreateStructuredOutput` aliases that runtime
- `Cognesy\Instructor\StructuredOutput` is registered as a public service

## Service File Status

Service files that are currently placeholder-only:
- `packages/symfony/resources/config/agents.yaml`
- `packages/symfony/resources/config/sessions.yaml`
- `packages/symfony/resources/config/testing.yaml`

Service files that now back real package behavior:
- `packages/symfony/resources/config/core.yaml`
- `packages/symfony/resources/config/polyglot.yaml`
- `packages/symfony/resources/config/events.yaml`
- `packages/symfony/resources/config/delivery.yaml`
- `packages/symfony/resources/config/agent_ctrl.yaml`
- `packages/symfony/resources/config/telemetry.yaml`
- `packages/symfony/resources/config/logging.yaml`
- `packages/symfony/resources/config/messenger.yaml`

That means the current implemented Symfony surface includes:
- bundle registration
- config tree and config translation
- AgentCtrl container entrypoints and runtime adapters
- core event wiring
- HTTP transport wiring
- public inference, embeddings, and structured output services
- native-agent registry and session-store seams
- telemetry services and lifecycle hooks
- logging services and presets
- progress projection, CLI observation, and Messenger delivery seams
- repository-local testing helpers and documented override patterns

Still intentionally repository-local or not yet published as public runtime API:
- classes under `packages/symfony/tests/Support`
- split-package publication bootstrap and Packagist registration

## Documentation

Reference docs:
- `packages/symfony/README.md`
- `packages/symfony/docs/overview.md`
- `packages/symfony/docs/configuration.md`
- `packages/symfony/docs/quickstart.md`
- `packages/symfony/docs/runtime-surfaces.md`
- `packages/symfony/docs/sessions.md`
- `packages/symfony/docs/testing.md`
- `packages/symfony/docs/telemetry.md`
- `packages/symfony/docs/logging.md`
- `packages/symfony/docs/delivery.md`
- `packages/symfony/docs/operations.md`
- `packages/symfony/docs/migration.md`
