---
title: Configuration and Runtime
description: Configure Decision presets, explicit runtimes, retries, events, and custom drivers.
---

<!-- markdownlint-disable MD013 -->

## TypeSafe preset

Set `TYPESAFE_API_KEY`, then use the bundled preset:

```php
use Cognesy\Polyglot\Decision\Decision;

$decision = Decision::using('typesafe');
```

The preset selects the `typesafe` driver, `https://api.typesafe.ai/v1/systemone`, and the `jev-latest` model alias. Application Decision configuration lives under the `sdm` group:

```yaml
# config/sdm/default.yaml
defaultPreset: typesafe
```

```yaml
# config/sdm/presets/typesafe.yaml
driver: typesafe
apiUrl: 'https://api.typesafe.ai/v1'
apiKey: '${TYPESAFE_API_KEY}'
endpoint: '/systemone'
model: 'jev-latest'
```

`DecisionConfig` supports `fromDefaults()`, `fromPreset()`, `presetNames()`, `fromArray()`, `fromDsn()`, and immutable `withOverrides()`. Use `toRedactedArray()` rather than `toArray()` when configuration may reach logs or diagnostics.

```php
use Cognesy\Polyglot\Decision\Config\DecisionConfig;

$config = DecisionConfig::fromPreset('typesafe')
    ->withOverrides(['model' => 'jev-latest']);
```

A usable configuration requires non-empty `driver`, `apiUrl`, `apiKey`, `endpoint`, and `model` fields. The URL must be HTTP(S), and the endpoint must start with `/`.

## Facade, provider, and runtime

Use the facade for ordinary calls:

```php
$answers = Decision::fromConfig($config)
    ->with(input: $state, questions: $questions)
    ->get();
```

Use `DecisionProvider` when application composition needs reusable config overrides, a model override, or an explicit driver. Use `DecisionRuntime` when it needs an event root, HTTP client, driver registry, or retry-delay implementation.

```php
use Cognesy\Polyglot\Decision\Decision;
use Cognesy\Polyglot\Decision\DecisionRuntime;

$runtime = DecisionRuntime::fromConfig(
    config: $config,
    events: $events,
    httpClient: $httpClient,
);

$decision = Decision::fromRuntime($runtime);
```

The facade is immutable: `withInput()`, `withQuestions()`, `withModel()`, `withRetryPolicy()`, `withRequest()`, and the combined `with()` method return a copy.

## Explicit requests

`DecisionRequest` is the provider-neutral execution input:

```php
use Cognesy\Polyglot\Decision\Data\DecisionRequest;

$request = new DecisionRequest(
    input: $state,
    questions: $questions,
    model: 'jev-latest',
);

$pending = $runtime->create($request);
```

`toArray()` serializes only the portable payload: input, typed questions, and optional model. Request IDs, retry policy, and telemetry correlation are execution-envelope concerns and are intentionally not serialized. `DecisionRequest::fromArray()` restores the portable payload.

## Retry policy

Decision performs one attempt unless a retry policy is supplied:

```php
use Cognesy\Polyglot\Decision\Config\DecisionRetryPolicy;

$policy = new DecisionRetryPolicy(
    maxAttempts: 3,
    baseDelayMs: 250,
    maxDelayMs: 4000,
    jitter: 'full',
);

$pending = $decision->withRetryPolicy($policy)->create();
```

The default retryable statuses are `408`, `429`, `500`, `502`, `503`, `504`, and `529`, plus timeout and network failures. Bounded `Retry-After` is honored by default. The Decision execution session owns retries, so do not add HTTP retry middleware around the same operation.

## Lifecycle events and telemetry

Register a specific listener or a wiretap on `DecisionRuntime`:

```php
$runtime
    ->onEvent(DecisionCompleted::class, $onCompleted)
    ->wiretap($observeEveryDecisionEvent);
```

The event pairs are:

- `DecisionStarted` followed by `DecisionCompleted` or `DecisionFailed`
- `DecisionAttemptStarted` followed by `DecisionAttemptSucceeded` or `DecisionAttemptFailed`

Polyglot projects them as an `sdm.decision` span with `sdm.decision.attempt` children. Default event and telemetry attributes omit input state, instructions, criteria, provider bodies, credentials, and exception messages.

## Custom drivers

A custom provider driver implements `CanProcessDecisionRequest::handle(DecisionRequest): DecisionResponse`. Register a class string or factory in `DecisionDriverRegistry`, then pass the registry to `Decision::using()`, `Decision::fromConfig()`, or `DecisionRuntime::fromConfig()`:

```php
use Cognesy\Polyglot\Decision\Creation\DecisionDriverRegistry;

$drivers = DecisionDriverRegistry::default()
    ->withDriver('acme', AcmeDecisionDriver::class);

$decision = Decision::fromConfig($config, drivers: $drivers);
```

Class-string drivers receive `DecisionConfig`, `CanSendHttpRequests`, and the event dispatcher. A factory receives the same three arguments. Provider adapters should translate between the stable `DecisionRequest`/`DecisionResponse` domain and the provider wire format; application code should not depend on the adapter classes.
