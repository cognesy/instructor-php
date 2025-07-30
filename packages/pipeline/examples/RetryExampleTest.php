<?php declare(strict_types=1);

require_once __DIR__ . '/RetryExample.php';

use Cognesy\Pipeline\Pipeline;

/**
 * Test suite for the retry middleware example
 */
class RetryExampleTest
{
    public static function testRetryUntilSuccess(): void
    {
        echo "\n🧪 TEST: Retry Until Success\n";
        
        $attemptCount = 0;
        
        [$retryConfig, $retryMiddleware] = RetryMiddleware::withConfig(
            maxAttempts: 5,
            baseDelay: 0.01, // Very fast for testing
            strategy: 'fixed'
        );

        $result = Pipeline::for('test-data')
            ->withTag($retryConfig)
            ->withMiddleware($retryMiddleware)
            ->through(function($data) use (&$attemptCount) {
                $attemptCount++;
                echo "  Attempt #$attemptCount\n";
                
                if ($attemptCount < 3) {
                    throw new \RuntimeException("Simulated failure #$attemptCount");
                }
                
                return "Success after $attemptCount attempts: $data";
            })
            ->process();

        assert($result->isSuccess(), "Should succeed after retries");
        assert($attemptCount === 3, "Should make exactly 3 attempts");
        
        $attempts = $result->computation()->all(RetryAttemptTag::class);
        assert(count($attempts) === 3, "Should record 3 attempts");
        assert($attempts[0]->failed(), "First attempt should fail");
        assert($attempts[1]->failed(), "Second attempt should fail");
        assert($attempts[2]->succeeded(), "Third attempt should succeed");
        
        echo "  ✅ Test passed: " . $result->value() . "\n";
    }

    public static function testRetryExhaustion(): void
    {
        echo "\n🧪 TEST: Retry Exhaustion\n";
        
        $attemptCount = 0;
        
        [$retryConfig, $retryMiddleware] = RetryMiddleware::withConfig(
            maxAttempts: 3,
            baseDelay: 0.01,
            strategy: 'fixed'
        );

        $result = Pipeline::for('failing-operation')
            ->withTag($retryConfig)
            ->withMiddleware($retryMiddleware)
            ->through(function($data) use (&$attemptCount) {
                $attemptCount++;
                echo "  Attempt #$attemptCount (will fail)\n";
                throw new \RuntimeException("Always fails: $data");
            })
            ->process();

        assert(!$result->isSuccess(), "Should fail after all retries exhausted");
        assert($attemptCount === 3, "Should make exactly 3 attempts");
        
        $attempts = $result->computation()->all(RetryAttemptTag::class);
        assert(count($attempts) === 3, "Should record 3 attempts");
        
        foreach ($attempts as $i => $attempt) {
            assert($attempt->failed(), "Attempt " . ($i + 1) . " should fail");
            assert($attempt->attemptNumber === $i + 1, "Attempt number should be correct");
        }
        
        echo "  ✅ Test passed: Failed as expected after 3 attempts\n";
    }

    public static function testRetryStrategies(): void
    {
        echo "\n🧪 TEST: Retry Strategy Timing\n";
        
        $strategies = ['fixed', 'linear', 'exponential'];
        
        foreach ($strategies as $strategy) {
            echo "  Testing $strategy strategy:\n";
            
            $attemptTimes = [];
            
            [$retryConfig, $retryMiddleware] = RetryMiddleware::withConfig(
                maxAttempts: 4,
                baseDelay: 0.1,
                strategy: $strategy
            );

            $sessionStart = microtime(true);
            
            $result = Pipeline::for("test-$strategy")
                ->withTag($retryConfig)
                ->withMiddleware($retryMiddleware)
                ->through(function($data) use (&$attemptTimes, $sessionStart) {
                    $attemptTimes[] = microtime(true) - $sessionStart;
                    
                    if (count($attemptTimes) < 3) {
                        throw new \RuntimeException("Fail attempt " . count($attemptTimes));
                    }
                    
                    return "Success with {$data} strategy";
                })
                ->process();

            assert($result->isSuccess(), "$strategy strategy should succeed");
            assert(count($attemptTimes) === 3, "Should make 3 attempts");
            
            // Verify timing intervals match strategy
            if (count($attemptTimes) >= 2) {
                $interval1 = $attemptTimes[1] - $attemptTimes[0];
                echo "    First retry delay: " . number_format($interval1 * 1000, 1) . "ms\n";
                
                if (count($attemptTimes) >= 3) {
                    $interval2 = $attemptTimes[2] - $attemptTimes[1];
                    echo "    Second retry delay: " . number_format($interval2 * 1000, 1) . "ms\n";
                    
                    switch ($strategy) {
                        case 'fixed':
                            assert(abs($interval1 - $interval2) < 0.02, "Fixed strategy should have similar delays");
                            break;
                        case 'linear':
                            assert($interval2 > $interval1, "Linear strategy should have increasing delays");
                            break;
                        case 'exponential':
                            assert($interval2 > $interval1 * 1.5, "Exponential strategy should have rapidly increasing delays");
                            break;
                    }
                }
            }
        }
        
        echo "  ✅ All retry strategies work correctly\n";
    }

    public static function testTagTracking(): void
    {
        echo "\n🧪 TEST: Tag Tracking Accuracy\n";
        
        [$retryConfig, $retryMiddleware] = RetryMiddleware::withConfig(
            maxAttempts: 4,
            baseDelay: 0.01,
            strategy: 'exponential'
        );

        $sessionId = 'test-session-' . uniqid();
        
        $result = Pipeline::for('tag-test')
            ->withTag(
                $retryConfig,
                new RetrySessionTag(
                    sessionId: $sessionId,
                    startTime: new \DateTimeImmutable(),
                    operation: 'tag_tracking_test'
                )
            )
            ->withMiddleware($retryMiddleware)
            ->through(function($data) {
                static $callCount = 0;
                $callCount++;
                
                if ($callCount <= 2) {
                    throw new \RuntimeException("Planned failure #$callCount");
                }
                
                return "Success on attempt $callCount";
            })
            ->process();

        $computation = $result->computation();
        
        // Verify session tag
        $session = $computation->last(RetrySessionTag::class);
        assert($session !== null, "Should have session tag");
        assert($session->sessionId === $sessionId, "Session ID should match");
        assert($session->operation === 'tag_tracking_test', "Operation should match");
        
        // Verify retry attempts
        $attempts = $computation->all(RetryAttemptTag::class);
        assert(count($attempts) === 3, "Should have 3 attempt tags");
        
        // Check attempt sequence
        for ($i = 0; $i < count($attempts); $i++) {
            $attempt = $attempts[$i];
            assert($attempt->attemptNumber === $i + 1, "Attempt number should be sequential");
            assert($attempt->timestamp instanceof \DateTimeImmutable, "Should have timestamp");
            assert($attempt->duration > 0, "Should track duration");
            
            if ($i < 2) {
                assert($attempt->failed(), "First two attempts should fail");
                assert($attempt->errorMessage !== null, "Failed attempts should have error message");
            } else {
                assert($attempt->succeeded(), "Last attempt should succeed");
                assert($attempt->errorMessage === null, "Successful attempt should have no error");
            }
        }
        
        echo "  ✅ All tags tracked correctly\n";
    }

    public static function testRetryableExceptions(): void
    {
        echo "\n🧪 TEST: Retryable vs Non-Retryable Exceptions\n";
        
        // Test retryable exception
        $retryConfig = new RetryConfigTag(
            maxAttempts: 3,
            baseDelay: 0.01,
            retryableExceptions: [\RuntimeException::class]
        );
        
        $attemptCount = 0;
        
        $result = Pipeline::for('retryable-test')
            ->withTag($retryConfig)
            ->withMiddleware(new RetryMiddleware())
            ->through(function($data) use (&$attemptCount) {
                $attemptCount++;
                throw new \RuntimeException("This should be retried");
            })
            ->process();

        assert(!$result->isSuccess(), "Should fail after retries");
        assert($attemptCount === 3, "Should retry RuntimeException 3 times");
        
        // Test non-retryable exception
        $attemptCount = 0;
        
        $result2 = Pipeline::for('non-retryable-test')
            ->withTag($retryConfig)
            ->withMiddleware(new RetryMiddleware())
            ->through(function($data) use (&$attemptCount) {
                $attemptCount++;
                throw new \InvalidArgumentException("This should NOT be retried");
            })
            ->process();

        assert(!$result2->isSuccess(), "Should fail immediately");
        assert($attemptCount === 1, "Should NOT retry InvalidArgumentException");
        
        echo "  ✅ Exception filtering works correctly\n";
    }

    public static function runAllTests(): void
    {
        echo "🚀 Running Retry Middleware Tests\n";
        echo "=================================\n";
        
        try {
            self::testRetryUntilSuccess();
            self::testRetryExhaustion();
            self::testRetryStrategies();
            self::testTagTracking();
            self::testRetryableExceptions();
            
            echo "\n🎉 All tests passed!\n";
        } catch (\Throwable $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            exit(1);
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename($_SERVER['PHP_SELF']) === 'RetryExampleTest.php') {
    RetryExampleTest::runAllTests();
}