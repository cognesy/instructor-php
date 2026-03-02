---
title: Reliability Middleware (Extras)
description: Retry, circuit breaker, and idempotency policies for production-grade transport behavior.
---

## Retry Policy

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Middleware\RetryPolicy;

$client = (new HttpClientBuilder())
    ->withRetryPolicy(new RetryPolicy(
        maxRetries: 3,
        baseDelayMs: 200,
    ))
    ->create();
```

`Retry-After` is respected only for numeric seconds or RFC7231 HTTP-date values (`D, d M Y H:i:s T`).

## Circuit Breaker Policy

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Middleware\ApcuCircuitBreakerStateStore;
use Cognesy\Http\Middleware\CircuitBreakerPolicy;

$client = (new HttpClientBuilder())
    ->withCircuitBreakerPolicy(
        new CircuitBreakerPolicy(
            failureThreshold: 5,
            openForSec: 30,
        ),
        new ApcuCircuitBreakerStateStore(),
    )
    ->create();
```

Use a shared state store for circuit breaker in multi-request runtimes (for example PHP-FPM). Without APCu, middleware falls back to in-process memory only.

## Idempotency Keys

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Middleware\IdempotencyMiddleware;

$client = (new HttpClientBuilder())
    ->withIdempotencyMiddleware(new IdempotencyMiddleware(
        headerName: 'Idempotency-Key',
        methods: ['POST'],
    ))
    ->create();
```

## Combine Policies

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Middleware\CircuitBreakerPolicy;
use Cognesy\Http\Middleware\IdempotencyMiddleware;
use Cognesy\Http\Middleware\RetryPolicy;

$client = (new HttpClientBuilder())
    ->withRetryPolicy(new RetryPolicy(maxRetries: 3))
    ->withCircuitBreakerPolicy(new CircuitBreakerPolicy())
    ->withIdempotencyMiddleware(new IdempotencyMiddleware())
    ->create();
```

## See Also

- [Middleware](10-middleware.md)
- [Record and replay (extras)](13-record-replay.md)
