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

Other bundled presets are `classifier-dev`, `jeff`, and `laya`. Each selects a
distinct driver; none aliases TypeSafe or acts as an automatic fallback. See
[classifier.dev](classifier-dev), [Jeff](jeff), and [Laya](laya) for their
provider-specific environment, semantics, and service requirements.

Every provider validates a non-empty driver/effective model and an HTTP(S)
target whose endpoint starts with `/`. Authentication is provider-specific:
TypeSafe requires a bearer API key, while a local or otherwise keyless driver
can validate the route and target without inventing a dummy secret.

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

## Decision model catalog

Catalog composition is optional and explicit:

```php
use Cognesy\Polyglot\Decision\Models\ModelCatalog;

$models = ModelCatalog::discover();
$runtime = DecisionRuntime::fromConfig(
    config: $config,
    models: $models,
);

$jev = $models->find('typesafe', 'jev-1.13.0');
$jev->maxRequestTokens;           // 64000 planning ceiling
$jev->maxStateAndQuestionTokens;  // 32000 planning ceiling
```

`maxRequestTokens` budgets shared state plus all questions. The separate
`maxStateAndQuestionTokens` budgets shared state plus each individual question.
These values are conservative planning ceilings from TypeSafe's published 64k
and 32k descriptions; Polyglot does not claim tokenizer-exact enforcement or
split requests automatically.

`jev-latest` has its own reviewed snapshot. It is not resolved dynamically to a
pinned record, so pin `jev-1.13.0` when stable constraints matter. Missing exact
routes return a typed `DecisionModel` with nullable unknown facts.

After execution, prefer the exact model ID returned by the provider for pricing:

```php
use Cognesy\Polyglot\Decision\Pricing\FlatRateCostCalculator;

$actual = $models->find('typesafe', $response->model());
$cost = $actual->pricing === null
    ? null
    : (new FlatRateCostCalculator)->calculate($response->usage(), $actual->pricing);
```

The calculator returns `null` when a nonzero rate needs usage the provider did
not report. An explicit zero rate is known free and requires no token count.
Do not fall back to an alias price when a provider returns an uncatalogued pinned
version.

Model records describe known request limits, pricing inputs, and primitive
semantics. They do not claim accuracy or calibration. Use
[provider evaluation](evaluation) against the returned model identity for those
questions.

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

`toArray()` serializes only the portable payload: input, typed questions, and optional model. Request IDs, retry policy, telemetry correlation, and an attached model profile are execution-envelope concerns and are intentionally not serialized. `DecisionRequest::fromArray()` restores the portable payload.

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

Polyglot projects them as an `sdm.decision` span with `sdm.decision.attempt`
children. When a model catalog is explicitly composed, lifecycle metadata also
includes the exact model key, catalog revision, and known native/projected/
unsupported primitive facts. Default event and telemetry attributes omit input
state, instructions, criteria, provider bodies, credentials, and exception
messages.

## Custom drivers

A custom provider driver implements `CanProcessDecisionRequest::handle(DecisionRequest): DecisionResponse`. Register a class string or factory in `DecisionDriverRegistry`, then pass the registry to `Decision::using()`, `Decision::fromConfig()`, or `DecisionRuntime::fromConfig()`:

```php
use Cognesy\Polyglot\Decision\Creation\DecisionDriverRegistry;

$drivers = DecisionDriverRegistry::default()
    ->withDriver('acme', AcmeDecisionDriver::class);

$decision = Decision::fromConfig($config, drivers: $drivers);
```

Class-string drivers receive `DecisionConfig`, `CanSendHttpRequests`, and the event dispatcher. A factory receives the same three arguments. Provider adapters should translate between the stable `DecisionRequest`/`DecisionResponse` domain and the provider wire format; application code should not depend on the adapter classes.
