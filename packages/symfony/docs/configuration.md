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
