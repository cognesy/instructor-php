---
title: Rate Limits
description: 'Learn how to handle rate limiting issues when using Polyglot.'
---

Provider rate limits can cause request failures during high traffic periods.

## Symptoms

- Error messages containing "rate limit exceeded," "too many requests," or "quota exceeded"
- HTTP status code 429

## Solutions

1. **Implement Retry Logic**: Add automatic retries with exponential backoff
```php
<?php
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Http\Exceptions\RequestException;

function withRetry(callable $fn, int $maxRetries = 3): mixed {
    $attempt = 0;
    $lastException = null;

    while ($attempt < $maxRetries) {
        try {
            return $fn();
        } catch (RequestException $e) {
            $lastException = $e;
            $attempt++;

            // Only retry on rate limit errors
            if (strpos($e->getMessage(), 'rate limit') === false &&
                $e->getCode() !== 429) {
                throw $e;
            }

            if ($attempt >= $maxRetries) {
                break;
            }

            // Exponential backoff
            $sleepTime = (2 ** $attempt);
            echo "Rate limit hit. Retrying in $sleepTime seconds...\n";
            sleep($sleepTime);
        }
    }

    throw $lastException;
}

// Usage
$inference = new Inference();

try {
    $response = withRetry(function() use ($inference) {
        return $inference->create(
            messages: 'What is the capital of France?'
        )->toText();
    });

    echo "Response: $response\n";
} catch (RequestException $e) {
    echo "All retry attempts failed: " . $e->getMessage() . "\n";
}
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
    $response = $inference->create(
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
    $responses[] = $inference->create(messages: $question)->toText();
}

// Better: Use a context-aware batch approach
$batchedQuestions = "Please answer the following questions:\n";
foreach ($questions as $i => $question) {
    $batchedQuestions .= ($i + 1) . ". $question\n";
}

$batchResponse = $inference->create(messages: $batchedQuestions)->toText();
// Then parse the batch response into individual answers
```

4. **Upgrade API Plan**: Consider upgrading to a higher tier with increased rate limits
