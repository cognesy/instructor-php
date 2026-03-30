# Symfony Configuration

The public config root for `packages/symfony` is reserved as:

```yaml
instructor:
```

The initial subtree map is:

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

This is intentionally defined before the runtime wiring lands so later tasks can extend one coherent config tree instead of introducing multiple unrelated roots.

## Baseline Runtime Shapes

The package now translates the core runtime subtrees into typed config objects owned by:

- `Cognesy\Polyglot\Inference\Config\LLMConfig`
- `Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig`
- `Cognesy\Instructor\Config\StructuredOutputConfig`
- `Cognesy\Http\Config\HttpClientConfig`

Preferred Symfony-oriented shapes are:

```yaml
instructor:
  connections:
    default: openai
    items:
      openai:
        driver: openai
        api_key: '%env(OPENAI_API_KEY)%'
        model: gpt-4o-mini

  embeddings:
    default: openai
    connections:
      openai:
        driver: openai
        model: text-embedding-3-small

  extraction:
    output_mode: tools
    max_retries: 2

  http:
    driver: symfony
    timeout: 20
    connect_timeout: 5
```

For migration safety, the translator also tolerates flatter connection maps such as `instructor.connections.openai` and legacy `llm.presets.*` lookups.

The current Symfony config tree is strict about subtree shape for the shipped core runtime surface:

- `connections`, `embeddings`, `extraction`, `http`, and `events` must be arrays
- flat connection maps are still normalized into `items` or `connections` internally
- provider-specific extra connection keys such as `temperature` are preserved and forwarded into runtime options
- malformed subtree shapes now fail during Symfony config processing instead of silently degrading to defaults

## HTTP Transport Rules

The baseline transport rules are:

- `instructor.http.driver: symfony` uses the bundle-owned HTTP contract and prefers Symfony's `http_client` service when it is available
- `instructor.http.driver: framework` and `instructor.http.driver: http_client` are normalized to the same Symfony transport
- other driver values such as `curl` or `guzzle` keep using the configured bundled HTTP driver explicitly

This keeps transport selection explicit while avoiding request-lifecycle assumptions in CLI, worker, and test contexts.

## Event Bridge Rules

The bundle now owns the `CanHandleEvents` service and uses a package-owned dispatcher with an optional Symfony parent bridge.

- `instructor.events.dispatch_to_symfony: true` keeps framework listeners connected through Symfony's event dispatcher
- `instructor.events.dispatch_to_symfony: false` keeps runtime events inside the package-owned bus only

This keeps runtime listeners and wiretaps package-local while still allowing Symfony applications to observe the same event stream when they want to.

See `packages/symfony/docs/delivery.md` for the distinction between internal wiretaps, the projected progress bus, Symfony listener delivery, Messenger flows, and CLI observation helpers.

## Session Persistence Config

The package now defines an explicit `instructor.sessions` subtree:

```yaml
instructor:
  sessions:
    store: memory # memory | file

    file:
      directory: '%kernel.cache_dir%/instructor/agent-sessions'
```

Rules:

- `store: memory` keeps the native-agent runtime process-local and ephemeral
- `store: file` enables the first supported persisted adapter for Symfony
- `file.directory` controls where JSON session payloads and lock files are stored
- applications that need a custom backend should replace `Cognesy\Agents\Session\Contracts\CanStoreSessions` in the container rather than expecting `packages/agents` to own Symfony-specific storage policy

Compatibility aliases currently accepted by the config tree:

- `sessions.driver` -> `sessions.store`
- `sessions.session_store` -> `sessions.store`
- `sessions.directory` -> `sessions.file.directory`

See `packages/symfony/docs/sessions.md` for the storage conventions, resume flow, and conflict semantics.

## Telemetry Config

The package now defines a typed `instructor.telemetry` subtree that matches the shared telemetry package model instead of leaving exporter composition to application glue:

```yaml
instructor:
  telemetry:
    enabled: false
    driver: null # null | otel | langfuse | logfire | composite
    service_name: symfony

    projectors:
      instructor: true
      polyglot: true
      http: true
      agent_ctrl: true
      agents: true

    http:
      capture_streaming_chunks: false

    drivers:
      composite:
        exporters: [otel, langfuse]

      otel:
        endpoint: '%env(default::INSTRUCTOR_TELEMETRY_OTEL_ENDPOINT)%'
        headers: { }

      langfuse:
        host: '%env(default::INSTRUCTOR_TELEMETRY_LANGFUSE_HOST)%'
        public_key: '%env(default::INSTRUCTOR_TELEMETRY_LANGFUSE_PUBLIC_KEY)%'
        secret_key: '%env(default::INSTRUCTOR_TELEMETRY_LANGFUSE_SECRET_KEY)%'

      logfire:
        endpoint: '%env(default::INSTRUCTOR_TELEMETRY_LOGFIRE_ENDPOINT)%'
        write_token: '%env(default::INSTRUCTOR_TELEMETRY_LOGFIRE_WRITE_TOKEN)%'
        headers: { }
```

Rules:

- `enabled: false` keeps telemetry fully optional in the baseline package path
- `driver` selects exactly one exporter strategy for the package-owned telemetry service graph
- `driver: composite` fans out to the ordered `drivers.composite.exporters` list
- `projectors` controls which runtime surfaces should attach to the shared telemetry bridge once telemetry wiring is enabled
- `http.capture_streaming_chunks` exists because chunk capture is useful for debugging but too noisy for a hard-coded default

This gives later service-wiring and lifecycle-hook tasks a stable public root without forcing Symfony adopters to invent their own exporter config conventions first.
See `packages/symfony/docs/telemetry.md` for the runtime bridge, projector ownership, and lifecycle behavior that now hangs off this subtree.
See `packages/symfony/docs/operations.md` for how the same telemetry surface behaves in web, API, Messenger, and CLI app shapes.

## AgentCtrl Config Model

The package now defines the public `instructor.agent_ctrl` config subtree and uses it to drive both builder registration and runtime-context policy.

Preferred shape:

```yaml
instructor:
  agent_ctrl:
    enabled: false
    default_backend: claude_code

    defaults:
      timeout: 300
      working_directory: '%kernel.project_dir%'
      sandbox_driver: host

    execution:
      transport: sync
      allow_cli: true
      allow_http: false
      allow_messenger: true

    continuation:
      mode: fresh
      session_key: agent_ctrl_session_id
      persist_session_id: true
      allow_cross_context_resume: true

    backends:
      claude_code:
        model: claude-sonnet-4-20250514
      codex:
        model: codex
      opencode:
        model: anthropic/claude-sonnet-4-20250514
      pi:
        enabled: false
      gemini:
        enabled: false
```

Notes:

- `defaults` carries the shared builder options that map cleanly onto `AgentCtrlConfig`: `model`, `timeout`, `working_directory`, and `sandbox_driver`
- `execution` captures framework policy for sync versus Messenger-triggered execution and whether CLI, HTTP, or Messenger entrypoints are allowed
- `continuation` captures the default continuation mode, session-key naming, and whether explicit `resume_session` handoff is allowed across runtime contexts
- `backends` holds per-agent overrides for `claude_code`, `codex`, `opencode`, `pi`, and `gemini`

Runtime services driven by this config:

- `Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrl` is the backend-aware builder entrypoint
- `Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrlRuntimes` builds context adapters for `cli`, `http`, and `messenger`
- named service IDs exist for `instructor.agent_ctrl.runtime.cli`, `instructor.agent_ctrl.runtime.http`, and `instructor.agent_ctrl.runtime.messenger`
- CLI and HTTP runtimes only allow inline `execute()` / `executeStreaming()` when `execution.transport` is `sync`
- the Messenger runtime can still execute inline inside a worker process even when the app-level transport is `messenger`
- runtime adapters expose `continuationPolicy()`, `continuation()`, `continueLast()`, `resumeSession()`, and `handoff()` so Symfony code can create same-context `continue_last` references locally and pass explicit `resume_session` handoff references from web or CLI entrypoints into Messenger workers

Compatibility aliases currently accepted by the config tree:

- `default_agent` -> `default_backend`
- `directory` -> `working_directory`
- `sandbox` -> `sandbox_driver`
- top-level backend keys such as `agent_ctrl.codex.*` are normalized into `agent_ctrl.backends.codex.*`

This keeps the public Symfony shape explicit while still tolerating the flatter Laravel-style config keys during migration and early package adoption.

## Delivery Config

The package now defines a typed `instructor.delivery` subtree for progress projection, optional CLI observation, and Messenger-backed async work:

```yaml
instructor:
  delivery:
    progress:
      enabled: true
    cli:
      enabled: false
      use_colors: true
      show_timestamps: true
    messenger:
      enabled: true
      bus_service: message_bus
      observe_events:
        - App\Runtime\Event\ProjectCompleted
        - Cognesy\Agents\Session\Events\SessionSaved
```

Rules:

- `delivery.progress.enabled: true` keeps the projected progress bus attached to the package-owned runtime bus
- `delivery.cli.enabled: true` auto-attaches the built-in console observer to the projected progress bus
- `delivery.cli.use_colors` and `delivery.cli.show_timestamps` control the package-owned CLI printer
- `delivery.messenger.enabled: true` enables the package-owned Messenger delivery seam
- `delivery.messenger.bus_service` points the observation bridge at the Symfony bus service that should receive runtime observation messages
- `delivery.messenger.observe_events` is an explicit allow-list of runtime event classes or interfaces that should be forwarded as `RuntimeObservationMessage` envelopes

The package-owned delivery entrypoints are:

- `Cognesy\Instructor\Symfony\Delivery\Progress\Contracts\CanHandleProgressUpdates`
- `Cognesy\Instructor\Symfony\Delivery\Progress\RuntimeProgressUpdate`
- `Cognesy\Instructor\Symfony\Delivery\Cli\SymfonyCliObservationFormatter`
- `Cognesy\Instructor\Symfony\Delivery\Cli\SymfonyCliObservationPrinter`

- `Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteAgentCtrlPromptMessage`
- `Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteNativeAgentPromptMessage`
- `Cognesy\Instructor\Symfony\Delivery\Messenger\RuntimeObservationMessage`
- `Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteAgentCtrlPromptMessageHandler`
- `Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteNativeAgentPromptMessageHandler`

This keeps queue dispatch and progress projection explicit:

- applications dispatch package-owned execution messages instead of treating Symfony listeners as an async transport
- applications can consume `CanHandleProgressUpdates` when they need transport-agnostic lifecycle or streaming updates for SSE, WebSockets, Mercure, or polling
- CLI output stays opt-in and builds on the same projected progress bus instead of formatting the raw runtime bus directly
- the internal event bus remains the source of truth and can optionally forward selected runtime events into Messenger for queued observation work
- Messenger delivery remains separate from the projected progress bus so async work does not depend on console-formatting assumptions

See `packages/symfony/docs/operations.md` for the recommended production shape for web, API, Messenger, and CLI applications.
See `packages/symfony/docs/migration.md` for moving existing app-local Symfony delivery wiring onto this supported subtree.

## Native Agent Extension Rules

The native-agent surface now supports two complementary extension modes.

Autoconfiguration:

- services implementing `Cognesy\Agents\Tool\Contracts\ToolInterface` are autoconfigured into the native tool registry
- services implementing `Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability` are autoconfigured into the capability registry
- services whose class is `Cognesy\Agents\Template\Data\AgentDefinition` are autoconfigured into the definition registry
- services whose class is `Cognesy\Instructor\Symfony\Agents\SchemaRegistration` are autoconfigured into the schema registry

Manual tags remain supported through `Cognesy\Instructor\Symfony\Agents\AgentRegistryTags`:

- `AgentRegistryTags::TOOLS`
- `AgentRegistryTags::CAPABILITIES`
- `AgentRegistryTags::DEFINITIONS`
- `AgentRegistryTags::SCHEMAS`

Use autoconfiguration when the service type already makes the contribution obvious.
Use manual tags when you need an explicit override, especially for capability-name overrides via the `alias` tag attribute.

Example:

```yaml
services:
  App\Ai\Tools\SearchDocsTool:
    autowire: true
    autoconfigure: true

  app.ai.capability.review:
    class: App\Ai\Capability\ReviewCapability
    autowire: true
    tags:
      - { name: instructor.agent.capability, alias: review }
```

## Ownership Boundaries

`packages/symfony` owns:

- Symfony bundle registration
- config normalization and framework-facing defaults
- container wiring and service aliases
- migration of scattered Symfony glue into one supported framework package

Other packages keep their core responsibilities:

- `packages/events` keeps reusable event-bus primitives, including the raw Symfony bridge
- `packages/logging` keeps reusable logging primitives and factories
- core runtime packages keep domain logic; `packages/symfony` only provides framework integration

This boundary keeps the Symfony package batteries-included without duplicating core runtime logic.
Use `packages/symfony/docs/migration.md` when moving older event, logging, telemetry, or delivery glue under that boundary.
