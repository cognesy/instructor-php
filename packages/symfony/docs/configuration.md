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
