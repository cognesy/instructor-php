---
title: Errors and Testing
description: Handle Decision failures and test provider integrations without live API calls.
---

<!-- markdownlint-disable MD013 -->

## Failure classes

Provider HTTP failures are normalized into Decision-specific exceptions:

| Condition | Exception | Retriable by default |
| --- | --- | --- |
| HTTP `401` or `403` | `DecisionAuthenticationException` | No |
| HTTP `429` | `DecisionRateLimitException` | Yes |
| HTTP `408`, `5xx`, missing status, or network failure | `DecisionTransientException` | Yes |
| Other HTTP `4xx` | `DecisionInvalidRequestException` | No |
| Malformed or inconsistent success payload | `DecisionResponseException` | No |

All five extend `DecisionProviderException`, which exposes `statusCode`, `retryAfter`, and `isRetriable()`.

```php
use Cognesy\Polyglot\Decision\Exceptions\DecisionAuthenticationException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionProviderException;

try {
    $answers = $decision->get();
} catch (DecisionAuthenticationException $error) {
    // Correct credentials or configuration; retrying will not help.
} catch (DecisionProviderException $error) {
    if (! $error->isRetriable()) {
        // Reject or repair the request.
    }
}
```

Construction and local invariant failures use `InvalidArgumentException`. Examples include empty question IDs, duplicate IDs, an executable request with no questions, a Choice with no options, a Score with fewer than two levels, unsupported JSON values, and incomplete configuration.

Provider exception messages deliberately exclude request bodies, response bodies, API keys, state, and question content. Preserve that boundary in application logs.

## Deterministic test seams

Choose the shallowest seam that covers the behavior under test:

- Implement `CanProcessDecisionRequest` as an in-memory fake for application logic, facade behavior, pending execution, retries, and lifecycle events.
- Inject an HTTP client backed by `MockHttpDriver` to exercise provider request and response adapters, headers, payload shape, error classification, and provider request IDs.
- Use an opt-in live smoke only to verify real credentials, a prestarted service,
  and provider compatibility. Evaluate quality separately.

Inject a fake driver directly through a runtime:

```php
use Cognesy\Polyglot\Decision\Decision;
use Cognesy\Polyglot\Decision\DecisionRuntime;

$runtime = new DecisionRuntime(
    driver: $fakeDecisionDriver,
    events: $events,
    defaultModel: 'test-model',
);

$answers = Decision::fromRuntime($runtime)
    ->with(input: 'test state', questions: $questions)
    ->get();
```

For adapter tests, pass the configured mock client through `DecisionRuntime::fromConfig(..., httpClient: $httpClient)`. No ordinary unit or feature test should require provider credentials, network access, Python, model weights, or special hardware.

## Opt-in live smoke

The repository includes bounded, opt-in provider integration tests:

```bash
POLYGLOT_TYPESAFE_LIVE=1 php vendor/bin/pest packages/polyglot/tests/Integration/TypesafeLiveTest.php
POLYGLOT_CLASSIFIER_DEV_LIVE=1 php vendor/bin/pest packages/polyglot/tests/Integration/ClassifierDevLiveTest.php
POLYGLOT_JEFF_LIVE=1 php vendor/bin/pest packages/polyglot/tests/Integration/JeffLiveTest.php
POLYGLOT_LAYA_LIVE=1 php vendor/bin/pest packages/polyglot/tests/Integration/LayaLiveTest.php
```

Each test is skipped unless explicitly enabled. TypeSafe requires its API key;
classifier.dev may be keyless; Jeff and Laya require prestarted services. The
tests verify bounded transport and contract behavior without claiming quality
parity. Use the [provider evaluation](evaluation) workflow for labeled metrics.
