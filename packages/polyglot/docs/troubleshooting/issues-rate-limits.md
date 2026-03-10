---
title: Rate Limits
description: Diagnose and handle provider rate limiting and quota issues.
---

Provider rate limits restrict the number of requests or tokens you can consume within a time window. When you exceed these limits, the provider returns an HTTP 429 response and your request fails. Polyglot provides built-in retry policies to handle these transient failures, but sustained rate limiting requires application-level strategies.

## Symptoms

- HTTP status code 429 (Too Many Requests)
- Error messages containing "rate limit exceeded," "too many requests," or "quota exceeded"
- Requests that work in isolation but fail under load

## Use the Built-In Retry Policy

Polyglot can automatically retry failed requests with exponential backoff and jitter. Retries are opt-in and explicit -- you must attach an `InferenceRetryPolicy` to the inference builder:

```php
<?php

use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('openai')
    ->withRetryPolicy(new InferenceRetryPolicy(
        maxAttempts: 4,
        baseDelayMs: 250,
        maxDelayMs: 8000,
        jitter: 'full',
    ))
    ->withMessages('What is the capital of France?')
    ->get();
```

### Retry Policy Parameters

| Parameter | Default | Description |
|---|---|---|
| `maxAttempts` | `1` | Total number of attempts (1 means no retries) |
| `baseDelayMs` | `250` | Base delay in milliseconds before the first retry |
| `maxDelayMs` | `8000` | Maximum delay cap in milliseconds |
| `jitter` | `'full'` | Jitter strategy: `none`, `full`, or `equal` |
| `retryOnStatus` | `[408, 429, 500, 502, 503, 504]` | HTTP status codes that trigger a retry |
| `retryOnExceptions` | `[TimeoutException, NetworkException]` | Exception classes that trigger a retry |

The delay between retries uses exponential backoff: `baseDelayMs * 2^(attempt - 1)`, capped at `maxDelayMs`. The jitter strategy adds randomness to avoid thundering herd problems:

- **`none`** -- no randomness, uses the exact computed delay
- **`full`** -- random delay between 0 and the computed delay
- **`equal`** -- half the computed delay plus a random value up to half the computed delay

### Length Recovery

The retry policy also supports automatic recovery when a response is truncated due to token limits:

```php
<?php

use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('openai')
    ->withRetryPolicy(new InferenceRetryPolicy(
        maxAttempts: 3,
        lengthRecovery: 'continue',       // or 'increase_max_tokens'
        lengthMaxAttempts: 2,
        lengthContinuePrompt: 'Continue.',
        maxTokensIncrement: 512,
    ))
    ->withMessages('Write a detailed essay about climate change.')
    ->get();
```

## Retry Policy for Embeddings

Embeddings requests use a separate policy class with the same interface:

```php
<?php

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;

$retryPolicy = new EmbeddingsRetryPolicy(
    maxAttempts: 3,
    baseDelayMs: 500,
    maxDelayMs: 10000,
    jitter: 'full',
);
```

## Application-Level Throttling

When retries alone are not enough, implement request throttling in your application to stay within the provider's rate limits:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

class RateLimiter
{
    private float $lastRequestTime = 0;
    private float $minTimeBetweenRequests;

    public function __construct(int $requestsPerMinute = 60) {
        $this->minTimeBetweenRequests = 60.0 / $requestsPerMinute;
    }

    public function waitIfNeeded(): void {
        $elapsed = microtime(true) - $this->lastRequestTime;

        if ($elapsed < $this->minTimeBetweenRequests) {
            usleep((int) (($this->minTimeBetweenRequests - $elapsed) * 1_000_000));
        }

        $this->lastRequestTime = microtime(true);
    }
}

$limiter = new RateLimiter(requestsPerMinute: 30);

for ($i = 0; $i < 10; $i++) {
    $limiter->waitIfNeeded();

    $text = Inference::using('openai')
        ->withMessages("This is request $i")
        ->get();

    echo "Response $i: $text\n";
}
```

## Batch Requests to Reduce Volume

Instead of making many small requests, combine related questions into a single prompt when the use case allows:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

// Instead of N separate requests...
$questions = [
    'What is the capital of France?',
    'What is the capital of Germany?',
    'What is the capital of Japan?',
];

// ...combine them into one request
$batchPrompt = "Answer each question on its own line:\n";
foreach ($questions as $i => $q) {
    $batchPrompt .= ($i + 1) . ". $q\n";
}

$text = Inference::using('openai')
    ->withMessages($batchPrompt)
    ->get();
```

This reduces the number of API calls from N to 1, dramatically lowering rate limit pressure.

## Additional Strategies

- **Switch providers or models.** Different providers and models have different rate limits. If one provider is heavily throttled, route some requests to another.
- **Upgrade your API plan.** Most providers offer higher rate limits on paid tiers.
- **Cache responses.** If the same prompts recur frequently, cache the results to avoid redundant API calls.
- **Use off-peak hours.** Some providers have lower contention during off-peak hours, reducing the likelihood of rate limiting.
- **Monitor usage.** Track your request volume and token consumption to anticipate rate limit issues before they affect users.
