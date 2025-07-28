# Pipeline Retry Middleware Example

This example demonstrates a comprehensive retry system implementation using Pipeline middleware and envelope stamps for tracking retry attempts.

## Features

### 🔄 Retry Middleware
- **Configurable retry logic** with multiple strategies (fixed, linear, exponential backoff)
- **Exception filtering** - only retry specified exception types
- **Attempt tracking** - records every retry attempt with timestamps and duration
- **Automatic delay calculation** based on strategy and attempt number

### 📊 Retry Tracking Stamps
- **RetryAttemptStamp** - Records each individual attempt with outcome
- **RetryConfigStamp** - Configures retry behavior and strategy
- **RetrySessionStamp** - Tracks overall retry session metadata

### 🔍 Observability
- **Comprehensive logging** of all retry attempts
- **Detailed metrics** including timing and failure reasons
- **Session tracking** for correlating retry attempts

## Components

### Stamps

#### RetryAttemptStamp
```php
class RetryAttemptStamp {
    public readonly int $attemptNumber;
    public readonly DateTimeImmutable $timestamp;
    public readonly ?string $errorMessage;
    public readonly ?float $duration;
}
```

#### RetryConfigStamp
```php
class RetryConfigStamp {
    public readonly int $maxAttempts;
    public readonly float $baseDelay;
    public readonly string $strategy; // 'fixed', 'linear', 'exponential'
    public readonly array $retryableExceptions;
}
```

#### RetrySessionStamp
```php
class RetrySessionStamp {
    public readonly string $sessionId;
    public readonly DateTimeImmutable $startTime;
    public readonly string $operation;
}
```

### Middleware

#### RetryMiddleware
The core retry logic middleware that:
- Executes processors and catches exceptions
- Implements delay strategies (fixed, linear, exponential backoff)
- Records attempt outcomes as stamps
- Decides whether to retry based on configuration

#### RetryLoggingMiddleware
Observability middleware that:
- Logs all retry attempts and outcomes
- Provides structured logging with context
- Tracks session and timing information

## Usage Examples

### Basic Retry
```php
[$retryConfig, $retryMiddleware] = RetryMiddleware::withConfig(
    maxAttempts: 3,
    baseDelay: 1.0,
    strategy: 'exponential'
);

$result = Pipeline::for($data)
    ->withStamp($retryConfig)
    ->withMiddleware($retryMiddleware)
    ->through(fn($x) => unreliableOperation($x))
    ->process();

if ($result->success()) {
    echo "Success: " . $result->value();
} else {
    // Analyze retry attempts
    $attempts = $result->envelope()->all(RetryAttemptStamp::class);
    echo "Failed after " . count($attempts) . " attempts";
}
```

### Advanced Configuration
```php
$retryConfig = new RetryConfigStamp(
    maxAttempts: 5,
    baseDelay: 0.5,
    maxDelay: 30.0,
    strategy: 'exponential',
    retryableExceptions: [
        \RuntimeException::class,
        \GuzzleHttp\Exception\ConnectException::class
    ]
);

$result = Pipeline::for($request)
    ->withStamp(
        $retryConfig,
        new RetrySessionStamp(
            sessionId: uniqid('api_'),
            startTime: new DateTimeImmutable(),
            operation: 'api_call'
        )
    )
    ->withMiddleware(
        new RetryLoggingMiddleware($logger),
        new RetryMiddleware()
    )
    ->through(fn($req) => $apiClient->call($req))
    ->process();
```

### Analyzing Retry History
```php
$envelope = $result->envelope();
$attempts = $envelope->all(RetryAttemptStamp::class);
$session = $envelope->last(RetrySessionStamp::class);

echo "Session: {$session->sessionId}\n";
echo "Operation: {$session->operation}\n";
echo "Total attempts: " . count($attempts) . "\n";

foreach ($attempts as $attempt) {
    $status = $attempt->succeeded() ? '✅' : '❌';
    $duration = number_format($attempt->duration * 1000, 2);
    echo "Attempt {$attempt->attemptNumber}: $status ({$duration}ms)";
    if ($attempt->failed()) {
        echo " - {$attempt->errorMessage}";
    }
    echo "\n";
}
```

## Running the Examples

### Interactive Examples
```bash
# Run the interactive demonstration
php examples/RetryExample.php

# Or use the runner
php examples/run_retry_demo.php --examples
```

### Test Suite
```bash
# Run comprehensive tests
php examples/RetryExampleTest.php

# Or use the runner
php examples/run_retry_demo.php --test
```

### Combined Demo
```bash
# Run both examples and tests
php examples/run_retry_demo.php
```

## Retry Strategies

### Fixed Delay
- Same delay between all retries
- `strategy: 'fixed'`
- Delay = `baseDelay`

### Linear Backoff
- Linearly increasing delays
- `strategy: 'linear'` 
- Delay = `baseDelay * attemptNumber`

### Exponential Backoff
- Exponentially increasing delays
- `strategy: 'exponential'`
- Delay = `baseDelay * 2^(attemptNumber-1)`

All strategies respect the `maxDelay` limit.

## Key Benefits

1. **Comprehensive Tracking** - Every retry attempt is recorded with detailed metadata
2. **Flexible Configuration** - Multiple retry strategies and exception filtering
3. **Observable** - Rich logging and stamp-based introspection
4. **Composable** - Works seamlessly with other Pipeline middleware
5. **Immutable** - All state changes create new envelope instances
6. **Type Safe** - Full PHP 8.2 type declarations throughout

## Architecture Highlights

- **Middleware Pattern** - Clean separation of retry logic from business logic
- **Stamp System** - Immutable metadata tracking with type-safe queries
- **Lazy Evaluation** - Retry logic only executes when results are needed
- **Error Recovery** - Graceful handling of various failure scenarios
- **Session Correlation** - Track related retry attempts across complex operations

This example demonstrates how Pipeline's middleware and stamp systems can be used to implement sophisticated cross-cutting concerns while maintaining clean, testable, and observable code.