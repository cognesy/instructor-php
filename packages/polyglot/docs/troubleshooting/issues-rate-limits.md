---
title: Rate Limits
description: 'Learn how to handle rate limiting issues when using Polyglot.'
---

Provider rate limits can cause request failures during high traffic periods.

## Symptoms

- Error messages containing "rate limit exceeded," "too many requests," or "quota exceeded"
- HTTP status code 429

## Solutions

1. **Use built-in resilience options**: Configure automatic retries with backoff/jitter

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = new Inference();

$response = $inference->with(
    messages: 'What is the capital of France?',
    options: [
        'resilience' => [
            'maxAttempts' => 4,
            'baseDelayMs' => 250,
            'maxDelayMs' => 8000,
            'jitter' => 'full',
            'retryOnStatus' => [429, 500, 502, 503, 504],
        ],
    ]
)->get();

echo "Response: $response\n";
```

2. **Request Throttling**: Limit the rate of requests from your application
```php
<?php
class RateLimiter {
    private $lastRequestTime = 0;
    private $requestsPerMinute;
    private $minTimeBetweenRequests;

    public function __construct(int $requestsPerMinute = 60) {
        $this->requestsPerMinute = $requestsPerMinute;
        $this->minTimeBetweenRequests = 60 / $requestsPerMinute;
    }

    public function waitIfNeeded(): void {
        $currentTime = microtime(true);
        $timeSinceLastRequest = $currentTime - $this->lastRequestTime;

        if ($timeSinceLastRequest < $this->minTimeBetweenRequests) {
            $waitTime = $this->minTimeBetweenRequests - $timeSinceLastRequest;
            usleep($waitTime * 1000000);
        }

        $this->lastRequestTime = microtime(true);
    }
}

// Usage
$limiter = new RateLimiter(30); // 30 requests per minute
$inference = new Inference();

for ($i = 0; $i < 10; $i++) {
    $limiter->waitIfNeeded();
    $response = $inference->with(
        messages: "This is request $i"
    )->toText();
    echo "Response $i: $response\n";
}
```

3. **Request Batching**: Combine multiple requests into batches when possible

```php
<?php
// Instead of making many small requests
$responses = [];
foreach ($questions as $question) {
    // This would hit rate limits quickly
    $responses[] = $inference->with(messages: $question)->get();
}

// Better: Use a context-aware batch approach
$batchedQuestions = "Please answer the following questions:\n";
foreach ($questions as $i => $question) {
    $batchedQuestions .= ($i + 1) . ". $question\n";
}

$batchResponse = $inference->with(messages: $batchedQuestions)->get();
// Then parse the batch response into individual answers
```

4. **Upgrade API Plan**: Consider upgrading to a higher tier with increased rate limits
