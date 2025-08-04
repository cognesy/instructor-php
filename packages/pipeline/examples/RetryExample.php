<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;

// ============================================================================
// TAGS FOR RETRY TRACKING
// ============================================================================

/**
 * Tag to track retry attempts and their outcomes
 */
class RetryAttemptTag implements TagInterface
{
    public function __construct(
        public readonly int $attemptNumber,
        public readonly \DateTimeImmutable $timestamp,
        public readonly ?string $errorMessage = null,
        public readonly ?float $duration = null,
    ) {}

    public function failed(): bool
    {
        return $this->errorMessage !== null;
    }

    public function succeeded(): bool
    {
        return $this->errorMessage === null;
    }
}

/**
 * Tag to configure retry behavior
 */
class RetryConfigTag implements TagInterface
{
    public function __construct(
        public readonly int $maxAttempts = 3,
        public readonly float $baseDelay = 1.0,
        public readonly float $maxDelay = 30.0,
        public readonly string $strategy = 'exponential', // 'linear', 'exponential', 'fixed'
        public readonly array $retryableExceptions = [\Exception::class],
    ) {}

    public function shouldRetry(\Throwable $exception, int $currentAttempt): bool
    {
        if ($currentAttempt >= $this->maxAttempts) {
            return false;
        }

        foreach ($this->retryableExceptions as $retryableClass) {
            if ($exception instanceof $retryableClass) {
                return true;
            }
        }

        return false;
    }

    public function getDelay(int $attemptNumber): float
    {
        return match ($this->strategy) {
            'fixed' => $this->baseDelay,
            'linear' => min($this->baseDelay * $attemptNumber, $this->maxDelay),
            'exponential' => min($this->baseDelay * pow(2, $attemptNumber - 1), $this->maxDelay),
            default => $this->baseDelay,
        };
    }
}

/**
 * Tag to track overall retry session metadata
 */
class RetrySessionTag implements TagInterface
{
    public function __construct(
        public readonly string $sessionId,
        public readonly \DateTimeImmutable $startTime,
        public readonly string $operation,
    ) {}
}

// ============================================================================
// RETRY MIDDLEWARE
// ============================================================================

/**
 * Middleware that implements retry logic with attempt tracking
 */
class RetryMiddleware implements CanControlStateProcessing
{
    public function handle(ProcessingState $state, callable $next): ProcessingState
    {
        // Get or create retry configuration
        $retryConfig = $state->lastTag(RetryConfigTag::class)
            ?? new RetryConfigTag();

        // Get or create retry session
        $retrySession = $state->lastTag(RetrySessionTag::class)
            ?? new RetrySessionTag(
                sessionId: uniqid('retry_', true),
                startTime: new \DateTimeImmutable(),
                operation: 'unknown'
            );

        // Add session tag if not present
        if (!$state->hasTag(RetrySessionTag::class)) {
            $state = $state->withTags($retrySession);
        }

        $currentAttempt = $state->countTag(RetryAttemptTag::class) + 1;
        $startTime = microtime(true);

        try {
            // Execute the next middleware/processor
            $result = $next($state);
            
            // Check if the result is a failure (even if no exception was thrown)
            if ($result instanceof ProcessingState && $result->result()->isFailure()) {
                $error = $result->result()->error();
                if ($error instanceof \Throwable) {
                    // Treat failed results as exceptions for retry logic
                    throw $error;
                }
            }
            
            // If successful, record the successful attempt
            $duration = microtime(true) - $startTime;
            $attemptTag = new RetryAttemptTag(
                attemptNumber: $currentAttempt,
                timestamp: new \DateTimeImmutable(),
                duration: $duration
            );

            return $result->withTags($attemptTag);

        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;
            
            // Record the failed attempt
            $attemptTag = new RetryAttemptTag(
                attemptNumber: $currentAttempt,
                timestamp: new \DateTimeImmutable(),
                errorMessage: $e->getMessage(),
                duration: $duration
            );

            $stateWithAttempt = $state->withTags($attemptTag);

            // Check if we should retry
            if ($retryConfig->shouldRetry($e, $currentAttempt)) {
                // Calculate and wait for delay
                $delay = $retryConfig->getDelay($currentAttempt);
                if ($delay > 0) {
                    usleep((int)($delay * 1000000)); // Convert to microseconds
                }

                // Recursive retry by calling handle again
                return $this->handle($stateWithAttempt, $next);
            }

            // No more retries, return failure state
            return $stateWithAttempt->failWith($e);
        }
    }

    /**
     * Static factory for easier configuration
     */
    public static function withConfig(
        int $maxAttempts = 3,
        float $baseDelay = 1.0,
        string $strategy = 'exponential'
    ): array {
        return [
            new RetryConfigTag($maxAttempts, $baseDelay, strategy: $strategy),
            new self()
        ];
    }
}

// ============================================================================
// LOGGING MIDDLEWARE FOR OBSERVABILITY
// ============================================================================

/**
 * Middleware to log retry attempts and outcomes
 */
class RetryLoggingMiddleware implements CanControlStateProcessing
{
    public function __construct(private ?\Psr\Log\LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new class implements \Psr\Log\LoggerInterface {
            public function emergency($message, array $context = []): void { echo "[EMERGENCY] $message\n"; }
            public function alert($message, array $context = []): void { echo "[ALERT] $message\n"; }
            public function critical($message, array $context = []): void { echo "[CRITICAL] $message\n"; }
            public function error($message, array $context = []): void { echo "[ERROR] $message\n"; }
            public function warning($message, array $context = []): void { echo "[WARNING] $message\n"; }
            public function notice($message, array $context = []): void { echo "[NOTICE] $message\n"; }
            public function info($message, array $context = []): void { echo "[INFO] $message\n"; }
            public function debug($message, array $context = []): void { echo "[DEBUG] $message\n"; }
            public function log($level, $message, array $context = []): void { echo "[$level] $message\n"; }
        };
    }

    public function handle(ProcessingState $state, callable $next): ProcessingState
    {
        $session = $state->lastTag(RetrySessionTag::class);
        $beforeAttempts = $state->countTag(RetryAttemptTag::class);

        $result = $next($state);

        $afterAttempts = $result->count(RetryAttemptTag::class);
        
        // If new attempts were added, log them
        if ($afterAttempts > $beforeAttempts) {
            $newAttempts = array_slice($result->all(RetryAttemptTag::class), $beforeAttempts);
            
            foreach ($newAttempts as $attempt) {
                if ($attempt->succeeded()) {
                    $this->logger->info("Retry attempt succeeded", [
                        'session_id' => $session?->sessionId,
                        'attempt' => $attempt->attemptNumber,
                        'duration' => $attempt->duration,
                        'operation' => $session?->operation
                    ]);
                } else {
                    $this->logger->warning("Retry attempt failed", [
                        'session_id' => $session?->sessionId,
                        'attempt' => $attempt->attemptNumber,
                        'error' => $attempt->errorMessage,
                        'duration' => $attempt->duration,
                        'operation' => $session?->operation
                    ]);
                }
            }
        }

        return $result;
    }
}

// ============================================================================
// EXAMPLE USAGE
// ============================================================================

/**
 * Simulated unreliable service that fails occasionally
 */
class UnreliableService
{
    public static int $callCount = 0;

    public static function makeApiCall(string $endpoint): array
    {
        self::$callCount++;
        
        // Simulate various failure scenarios
        if (self::$callCount === 1) {
            throw new \RuntimeException("Network timeout");
        }
        
        if (self::$callCount === 2) {
            throw new \RuntimeException("Service temporarily unavailable");
        }
        
        if (self::$callCount === 3) {
            // Success on third attempt
            return [
                'status' => 'success',
                'data' => ['id' => 123, 'name' => 'Test Data'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }

        return ['status' => 'success', 'data' => 'fallback'];
    }
}

// ============================================================================
// DEMO FUNCTIONS
// ============================================================================

function demonstrateBasicRetry(): void
{
    echo "\n=== BASIC RETRY EXAMPLE ===\n";

    [$retryConfig, $retryMiddleware] = RetryMiddleware::withConfig(
        maxAttempts: 5,
        baseDelay: 0.1, // Short delay for demo
        strategy: 'exponential'
    );

    $result = Pipeline::for('/api/users')
        ->withTags(
            $retryConfig,
            new RetrySessionTag(
                sessionId: 'demo_' . uniqid(),
                startTime: new \DateTimeImmutable(),
                operation: 'fetch_users'
            )
        )
        ->withMiddleware(
            new RetryLoggingMiddleware(),
            $retryMiddleware
        )
        ->through(function(string $endpoint) {
            echo "Attempting API call to: $endpoint\n";
            return UnreliableService::makeApiCall($endpoint);
        })
        ->create();

    if ($result->isSuccess()) {
        echo "✅ Operation succeeded!\n";
        echo "Result: " . json_encode($result->valueOr()) . "\n";
    } else {
        echo "❌ Operation failed after all retries\n";
        echo "Error: " . $result->exception()->getMessage() . "\n";
    }

    // Analyze retry attempts
    $state = $result->state();
    $attempts = $state->allTags(RetryAttemptTag::class);
    $session = $state->lastTag(RetrySessionTag::class);

    echo "\n📊 Retry Analysis:\n";
    echo "Session ID: {$session->sessionId}\n";
    echo "Total attempts: " . count($attempts) . "\n";
    
    foreach ($attempts as $attempt) {
        $status = $attempt->succeeded() ? '✅' : '❌';
        $duration = number_format($attempt->duration * 1000, 2);
        echo "  Attempt {$attempt->attemptNumber}: $status ({$duration}ms)";
        if ($attempt->failed()) {
            echo " - {$attempt->errorMessage}";
        }
        echo "\n";
    }
}

function demonstrateRetryWithProcessing(): void
{
    echo "\n=== RETRY WITH DATA PROCESSING ===\n";

    // Reset the service call count for new demo
    UnreliableService::$callCount = 0;

    [$retryConfig, $retryMiddleware] = RetryMiddleware::withConfig(
        maxAttempts: 4,
        baseDelay: 0.05,
        strategy: 'linear'
    );

    $result = Pipeline::for(['endpoint' => '/api/data', 'params' => ['limit' => 10]])
        ->withTags(
            $retryConfig,
            new RetrySessionTag(
                sessionId: 'processing_' . uniqid(),
                startTime: new \DateTimeImmutable(),
                operation: 'fetch_and_process_data'
            )
        )
        ->withMiddleware(
            new RetryLoggingMiddleware(),
            $retryMiddleware
        )
        ->through(function(array $request) {
            echo "🌐 Making API request: {$request['endpoint']}\n";
            $response = UnreliableService::makeApiCall($request['endpoint']);
            return array_merge($response, ['request' => $request]);
        })
        ->through(function(array $response) {
            echo "🔄 Processing response data\n";
            if ($response['status'] !== 'success') {
                throw new \RuntimeException("Invalid response status: {$response['status']}");
            }
            
            return [
                'processed_data' => $response['data'],
                'metadata' => [
                    'processed_at' => date('Y-m-d H:i:s'),
                    'original_request' => $response['request']
                ]
            ];
        })
        ->create();

    if ($result->isSuccess()) {
        echo "✅ Data processing completed!\n";
        echo "Processed data: " . json_encode($result->valueOr(), JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "❌ Data processing failed\n";
        echo "Error: " . $result->exception()->getMessage() . "\n";
    }

    // Show attempt history
    $state = $result->state();
    $attempts = $state->allTags(RetryAttemptTag::class);
    
    echo "\n📈 Processing Attempts:\n";
    foreach ($attempts as $attempt) {
        $status = $attempt->succeeded() ? '✅ SUCCESS' : '❌ FAILED';
        $duration = number_format($attempt->duration * 1000, 2);
        echo "  #{$attempt->attemptNumber}: $status ({$duration}ms)";
        if ($attempt->failed()) {
            echo " - {$attempt->errorMessage}";
        }
        echo " at {$attempt->timestamp->format('H:i:s.u')}\n";
    }
}

function demonstrateRetryConfiguration(): void
{
    echo "\n=== DIFFERENT RETRY STRATEGIES ===\n";

    $strategies = [
        'fixed' => 'Fixed 100ms delay',
        'linear' => 'Linear backoff (100ms, 200ms, 300ms...)',
        'exponential' => 'Exponential backoff (100ms, 200ms, 400ms...)'
    ];

    foreach ($strategies as $strategy => $description) {
        echo "\n🔧 Testing $description:\n";
        
        // Reset service for each test
        UnreliableService::$callCount = 0;

        [$retryConfig, $retryMiddleware] = RetryMiddleware::withConfig(
            maxAttempts: 3,
            baseDelay: 0.1,
            strategy: $strategy
        );

        $start = microtime(true);
        
        $result = Pipeline::for("test-$strategy")
            ->withTags($retryConfig)
            ->withMiddleware($retryMiddleware)
            ->through(function(string $test) {
                return UnreliableService::makeApiCall("/api/$test");
            })
            ->create();

        $totalTime = microtime(true) - $start;
        
        $attempts = $result->state()->allTags(RetryAttemptTag::class);
        echo "  Total attempts: " . count($attempts) . "\n";
        echo "  Total time: " . number_format($totalTime * 1000, 2) . "ms\n";
        echo "  Result: " . ($result->isSuccess() ? '✅ Success' : '❌ Failed') . "\n";
    }
}

// ============================================================================
// RUN EXAMPLES
// ============================================================================

if (php_sapi_name() === 'cli') {
    echo "🚀 Pipeline Retry Middleware Demo\n";
    echo "==================================\n";

    demonstrateBasicRetry();
    demonstrateRetryWithProcessing();
    demonstrateRetryConfiguration();

    echo "\n✨ Demo completed!\n";
}