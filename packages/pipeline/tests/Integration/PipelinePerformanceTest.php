<?php declare(strict_types=1);

use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\PipelineMiddlewareInterface;
use Cognesy\Utils\Chain\ResultChain;

/**
 * Performance and Memory Consumption Test for Pipeline class
 *
 * Tests the performance characteristics of Pipeline vs raw PHP vs ResultChain
 * with string/array processing pipeline: $text -> split into words -> trim each
 * -> remove shorter than 3 chars -> lowercase each -> finalize with converting back to string.
 */

// Configuration: Set number of iterations for all performance tests
const PERFORMANCE_TEST_ITERATIONS = 1000;

/**
 * Performance measurement and test execution class
 */
class PerformanceTester
{
    private int $iterations;
    private string $testText;

    public function __construct(int $iterations = PERFORMANCE_TEST_ITERATIONS) {
        $this->iterations = $iterations;
        $this->testText = 'The quick brown fox jumps over the lazy dog. This is a test string for performance measurement with multiple words of varying lengths.';
    }

    /**
     * Execute a performance test with timing and memory measurements
     */
    public function measurePerformance(callable $testFunction, string $testName): array {
        gc_collect_cycles();

        $startMemory = memory_get_usage(true);
        $startTime = hrtime(true);

        $result = null;
        for ($i = 0; $i < $this->iterations; $i++) {
            $result = $testFunction($this->testText);
        }

        $endTime = hrtime(true);
        $endMemory = memory_get_usage(true);

        $totalTimeMs = ($endTime - $startTime) / 1_000_000;
        $avgTimePerIterationUs = ($endTime - $startTime) / ($this->iterations * 1000);
        $memoryUsageBytes = $endMemory - $startMemory;
        $avgMemoryPerIterationBytes = $memoryUsageBytes / $this->iterations;

        $this->displayResults($testName, $totalTimeMs, $avgTimePerIterationUs, $memoryUsageBytes, $avgMemoryPerIterationBytes);

        return [
            'result' => $result,
            'totalTimeMs' => $totalTimeMs,
            'avgTimePerIterationUs' => $avgTimePerIterationUs,
            'memoryUsageBytes' => $memoryUsageBytes,
            'avgMemoryPerIterationBytes' => $avgMemoryPerIterationBytes,
        ];
    }

    /**
     * Display performance results in a consistent format
     */
    private function displayResults(string $testName, float $totalTimeMs, float $avgTimePerIterationUs, int $memoryUsageBytes, float $avgMemoryPerIterationBytes): void {
        echo "\n=== " . strtoupper($testName) . " ===\n";
        echo "Total execution time for {$this->iterations} iterations: " . number_format($totalTimeMs, 2) . " ms\n";
        echo "Average per iteration: " . number_format($avgTimePerIterationUs, 2) . " μs\n";
        echo "Total memory consumption: {$memoryUsageBytes} bytes\n";
        echo "Average memory per iteration: " . number_format($avgMemoryPerIterationBytes, 2) . " bytes\n";
    }

    /**
     * Get the test text
     */
    public function getTestText(): string {
        return $this->testText;
    }

    /**
     * Get the number of iterations
     */
    public function getIterations(): int {
        return $this->iterations;
    }

    /**
     * Execute multiple test functions in different orders to compare execution order impact
     *
     * @param array $testFunctions Array of ['name' => string, 'function' => callable] pairs
     * @return array Results organized by execution order
     */
    public function compareExecutionOrders(array $testFunctions): array {
        // Always start with raw PHP if present, otherwise preserve order
        $reorderedTests = $this->prioritizeRawPhp($testFunctions);

        $results = [
            'provided_order' => [],
            'reverse_order' => [],
            'shift_order' => [],
        ];

        // 1) Execute in provided order (with raw PHP first)
        echo "\n=== EXECUTION ORDER: PROVIDED (RAW PHP FIRST) ===\n";
        foreach ($reorderedTests as $test) {
            $metrics = $this->measurePerformance($test['function'], $test['name']);
            $results['provided_order'][$test['name']] = $metrics;
        }

        // 2) Execute in reverse order
        echo "\n=== EXECUTION ORDER: REVERSE ===\n";
        $reversedTests = array_reverse($reorderedTests);
        foreach ($reversedTests as $test) {
            $metrics = $this->measurePerformance($test['function'], $test['name'] . ' (Reverse Order)');
            $results['reverse_order'][$test['name']] = $metrics;
        }

        // 3) Execute in shifted order (move first element to end)
        echo "\n=== EXECUTION ORDER: SHIFTED ===\n";
        $shiftedTests = $this->shiftArray($reorderedTests);
        foreach ($shiftedTests as $test) {
            $metrics = $this->measurePerformance($test['function'], $test['name'] . ' (Shifted Order)');
            $results['shift_order'][$test['name']] = $metrics;
        }

        $this->displayOrderComparisonSummary($results);

        return $results;
    }

    /**
     * Reorder tests to ensure raw PHP comes first
     */
    private function prioritizeRawPhp(array $testFunctions): array {
        $rawPhpIndex = null;

        // Find raw PHP test
        foreach ($testFunctions as $index => $test) {
            if (stripos($test['name'], 'raw php') !== false ||
                stripos($test['name'], 'baseline') !== false) {
                $rawPhpIndex = $index;
                break;
            }
        }

        // If raw PHP found, move it to the beginning
        if ($rawPhpIndex !== null) {
            $rawPhpTest = $testFunctions[$rawPhpIndex];
            unset($testFunctions[$rawPhpIndex]);
            array_unshift($testFunctions, $rawPhpTest);
        }

        return array_values($testFunctions);
    }

    /**
     * Shift array elements (move first to end)
     */
    private function shiftArray(array $array): array {
        if (count($array) <= 1) {
            return $array;
        }

        $first = array_shift($array);
        $array[] = $first;

        return $array;
    }

    /**
     * Display execution order comparison summary
     */
    private function displayOrderComparisonSummary(array $results): void {
        echo "\n=== EXECUTION ORDER IMPACT SUMMARY ===\n";

        // Collect all test names
        $testNames = array_keys($results['provided_order']);

        foreach ($testNames as $testName) {
            echo "\n--- {$testName} ---\n";

            $providedTime = $results['provided_order'][$testName]['avgTimePerIterationUs'];
            $reverseTime = $results['reverse_order'][$testName]['avgTimePerIterationUs'];
            $shiftTime = $results['shift_order'][$testName]['avgTimePerIterationUs'];

            echo "Provided Order: " . number_format($providedTime, 2) . " μs\n";
            echo "Reverse Order:  " . number_format($reverseTime, 2) . " μs ";
            echo "(" . $this->calculatePercentageDiff($providedTime, $reverseTime) . ")\n";
            echo "Shift Order:    " . number_format($shiftTime, 2) . " μs ";
            echo "(" . $this->calculatePercentageDiff($providedTime, $shiftTime) . ")\n";
        }

        echo "\nNote: Percentage shows difference from provided order execution.\n";
        echo "Positive values indicate slower execution, negative values indicate faster execution.\n";
    }

    /**
     * Calculate percentage difference between two values
     */
    private function calculatePercentageDiff(float $baseline, float $comparison): string {
        if ($baseline == 0) {
            return "N/A";
        }

        $diff = (($comparison - $baseline) / $baseline) * 100;
        $sign = $diff >= 0 ? '+' : '';

        return $sign . number_format($diff, 1) . '%';
    }
}

// Test middleware implementations
class TestTimerMiddleware implements PipelineMiddlewareInterface
{
    private static array $timings = [];

    public function handle(Computation $computation, callable $next): Computation {
        $start = hrtime(true);
        $result = $next($computation);
        $end = hrtime(true);

        self::$timings[] = ($end - $start) / 1000; // microseconds
        return $result;
    }

    public static function getTimings(): array {
        return self::$timings;
    }

    public static function reset(): void {
        self::$timings = [];
    }
}

class TestRetryMiddleware implements PipelineMiddlewareInterface
{
    private int $maxRetries = 2;

    public function handle(Computation $computation, callable $next): Computation {
        $result = $next($computation);

        if ($result->isFailure() && $this->maxRetries > 0) {
            $this->maxRetries--;
            return $this->handle($computation, $next);
        }

        return $result;
    }
}

class TestErrorLoggerMiddleware implements PipelineMiddlewareInterface
{
    private static array $errors = [];

    public function handle(Computation $computation, callable $next): Computation {
        $result = $next($computation);

        if ($result->isFailure()) {
            self::$errors[] = $result->exception()?->getMessage() ?? 'Unknown error';
        }

        return $result;
    }

    public static function getErrors(): array {
        return self::$errors;
    }

    public static function reset(): void {
        self::$errors = [];
    }
}

// Dummy logger for hooks testing
class TestDummyLogger
{
    private static array $logs = [];

    public static function log(string $message): void {
        self::$logs[] = $message;
    }

    public static function getLogs(): array {
        return self::$logs;
    }

    public static function reset(): void {
        self::$logs = [];
    }
}

describe('Pipeline Performance and Memory Tests', function () {

    beforeEach(function () {
        TestTimerMiddleware::reset();
        TestErrorLoggerMiddleware::reset();
        TestDummyLogger::reset();
    });

    it('measures performance - simple pipeline (no middleware, no hooks)', function () {
        $tester = new PerformanceTester();

        $testFunction = function ($testText) {
            return Pipeline::for($testText)
                ->through(fn($text) => explode(' ', $text))
                ->through(fn($words) => array_map('trim', $words))
                ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
                ->through(fn($words) => array_map('strtolower', $words))
                ->finally(fn($result) => implode(' ', $result->unwrap()))
                ->process();
        };

        $metrics = $tester->measurePerformance($testFunction, 'Simple Pipeline Performance');
        $result = $metrics['result'];

        expect($result->isSuccess())->toBeTrue();
        expect($result->value())->toContain('quick brown');
    });

    it('measures performance - pipeline with middleware', function () {
        $tester = new PerformanceTester();

        $testFunction = function ($testText) {
            return Pipeline::for($testText)
                ->withMiddleware(
                    new TestTimerMiddleware(),
                    new TestRetryMiddleware(),
                    new TestErrorLoggerMiddleware(),
                )
                ->through(fn($text) => explode(' ', $text))
                ->through(fn($words) => array_map('trim', $words))
                ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
                ->through(fn($words) => array_map('strtolower', $words))
                ->finally(fn($result) => implode(' ', $result->unwrap()))
                ->process();
        };

        $metrics = $tester->measurePerformance($testFunction, 'Middleware Pipeline Performance');
        $result = $metrics['result'];

        expect($result->isSuccess())->toBeTrue();
        expect($result->value())->toContain('quick brown');
    });

    it('measures performance - pipeline with hooks', function () {
        $tester = new PerformanceTester();

        $testFunction = function ($testText) {
            return Pipeline::for($testText)
                ->beforeEach(fn($comp) => TestDummyLogger::log("Before: processing " . substr(json_encode($comp->result()->unwrap()), 0, 20)))
                ->afterEach(fn($comp) => TestDummyLogger::log("After: processed " . substr(json_encode($comp->result()->unwrap()), 0, 20)))
                ->through(fn($text) => explode(' ', $text))
                ->through(fn($words) => array_map('trim', $words))
                ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
                ->through(fn($words) => array_map('strtolower', $words))
                ->finally(fn($result) => implode(' ', $result->unwrap()))
                ->process();
        };

        $metrics = $tester->measurePerformance($testFunction, 'Hooks Pipeline Performance');
        $result = $metrics['result'];

        expect($result->isSuccess())->toBeTrue();
        expect($result->value())->toContain('quick brown');
        expect(count(TestDummyLogger::getLogs()))->toBeGreaterThan(0);
    });

    it('measures performance - pipeline with middleware and hooks', function () {
        $tester = new PerformanceTester();

        $testFunction = function ($testText) {
            return Pipeline::for($testText)
                ->withMiddleware(
                    new TestTimerMiddleware(),
                    new TestRetryMiddleware(),
                    new TestErrorLoggerMiddleware(),
                )
                ->beforeEach(fn($comp) => TestDummyLogger::log("Before: processing " . substr(json_encode($comp->result()->unwrap()), 0, 20)))
                ->afterEach(fn($comp) => TestDummyLogger::log("After: processed " . substr(json_encode($comp->result()->unwrap()), 0, 20)))
                ->through(fn($text) => explode(' ', $text))
                ->through(fn($words) => array_map('trim', $words))
                ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
                ->through(fn($words) => array_map('strtolower', $words))
                ->finally(fn($result) => implode(' ', $result->unwrap()))
                ->process();
        };

        $metrics = $tester->measurePerformance($testFunction, 'Middleware + Hooks Pipeline Performance');
        $result = $metrics['result'];

        expect($result->isSuccess())->toBeTrue();
        expect($result->value())->toContain('quick brown');
        expect(count(TestDummyLogger::getLogs()))->toBeGreaterThan(0);
    });

    it('baseline comparison - raw PHP implementation', function () {
        $tester = new PerformanceTester();

        $testFunction = function ($testText) {
            // Raw PHP implementation
            $words = explode(' ', $testText);
            $words = array_map('trim', $words);
            $words = array_filter($words, fn($word) => strlen($word) >= 3);
            $words = array_map('strtolower', $words);
            return implode(' ', $words);
        };

        $metrics = $tester->measurePerformance($testFunction, 'Raw PHP Baseline Performance');
        $result = $metrics['result'];

        expect($result)->toContain('quick brown');
    });

    it('baseline comparison - ResultChain implementation', function () {
        $tester = new PerformanceTester();

        $testFunction = function ($testText) {
            return ResultChain::for($testText)
                ->through(fn($text) => explode(' ', $text))
                ->through(fn($words) => array_map('trim', $words))
                ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
                ->through(fn($words) => array_map('strtolower', $words))
                ->then(fn($result) => implode(' ', $result->unwrap()))
                ->process();
        };

        $metrics = $tester->measurePerformance($testFunction, 'ResultChain Baseline Performance');
        $result = $metrics['result'];

        expect($result)->toContain('quick brown');
    });

    it('performance comparison summary', function () {
        echo "\n=== PERFORMANCE COMPARISON SUMMARY ===\n";
        echo "Run all performance tests above to see detailed metrics.\n";
        echo "Expected performance ranking (fastest to slowest):\n";
        echo "1. Raw PHP (baseline)\n";
        echo "2. ResultChain\n";
        echo "3. Simple Pipeline\n";
        echo "4. Pipeline with hooks\n";
        echo "5. Pipeline with middleware\n";
        echo "6. Pipeline with middleware + hooks\n";
        echo "\nMemory usage should follow similar pattern.\n";

        expect(true)->toBeTrue();
    });

    it('compares execution order impact on performance', function () {
        $tester = new PerformanceTester();

        // Define test functions for comparison
        $testFunctions = [
            [
                'name' => 'Simple Pipeline',
                'function' => function ($testText) {
                    return Pipeline::for($testText)
                        ->through(fn($text) => explode(' ', $text))
                        ->through(fn($words) => array_map('trim', $words))
                        ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
                        ->through(fn($words) => array_map('strtolower', $words))
                        ->finally(fn($result) => implode(' ', $result->unwrap()))
                        ->process();
                },
            ],
            [
                'name' => 'Middleware Pipeline',
                'function' => function ($testText) {
                    return Pipeline::for($testText)
                        ->withMiddleware(
                            new TestTimerMiddleware(),
                            new TestRetryMiddleware(),
                            new TestErrorLoggerMiddleware(),
                        )
                        ->through(fn($text) => explode(' ', $text))
                        ->through(fn($words) => array_map('trim', $words))
                        ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
                        ->through(fn($words) => array_map('strtolower', $words))
                        ->finally(fn($result) => implode(' ', $result->unwrap()))
                        ->process();
                },
            ],
            [
                'name' => 'Raw PHP Baseline',
                'function' => function ($testText) {
                    $words = explode(' ', $testText);
                    $words = array_map('trim', $words);
                    $words = array_filter($words, fn($word) => strlen($word) >= 3);
                    $words = array_map('strtolower', $words);
                    return implode(' ', $words);
                },
            ],
            [
                'name' => 'ResultChain Baseline',
                'function' => function ($testText) {
                    return ResultChain::for($testText)
                        ->through(fn($text) => explode(' ', $text))
                        ->through(fn($words) => array_map('trim', $words))
                        ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
                        ->through(fn($words) => array_map('strtolower', $words))
                        ->then(fn($result) => implode(' ', $result->unwrap()))
                        ->process();
                },
            ],
        ];

        // Execute tests in different orders and compare
        $results = $tester->compareExecutionOrders($testFunctions);

        // Verify that all orders produce valid results
        foreach ($results as $orderType => $orderResults) {
            foreach ($orderResults as $testName => $metrics) {
                expect($metrics['avgTimePerIterationUs'])->toBeGreaterThan(0);
                expect($metrics['result'])->not->toBeNull();
            }
        }

        expect(count($results))->toBe(3); // provided, reverse, shift orders
        expect(count($results['provided_order']))->toBe(4); // 4 test functions
    });

    it('validates all implementations produce identical results', function () {
        $tester = new PerformanceTester();
        $testText = $tester->getTestText();

        // Raw PHP
        $rawFunction = function ($testText) {
            $words = explode(' ', $testText);
            $words = array_map('trim', $words);
            $words = array_filter($words, fn($word) => strlen($word) >= 3);
            $words = array_map('strtolower', $words);
            return implode(' ', $words);
        };
        $rawResult = $rawFunction($testText);

        // Pipeline
        $pipelineFunction = function ($testText) {
            return Pipeline::for($testText)
                ->through(fn($text) => explode(' ', $text))
                ->through(fn($words) => array_map('trim', $words))
                ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
                ->through(fn($words) => array_map('strtolower', $words))
                ->finally(fn($result) => implode(' ', $result->unwrap()))
                ->process()
                ->value();
        };
        $pipelineResult = $pipelineFunction($testText);

        // ResultChain
        $chainFunction = function ($testText) {
            return ResultChain::for($testText)
                ->through(fn($text) => explode(' ', $text))
                ->through(fn($words) => array_map('trim', $words))
                ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
                ->through(fn($words) => array_map('strtolower', $words))
                ->then(fn($result) => implode(' ', $result->unwrap()))
                ->process();
        };
        $chainResult = $chainFunction($testText);

        expect($pipelineResult)->toBe($rawResult);
        expect($chainResult)->toBe($rawResult);

        echo "\n=== RESULT VALIDATION ===\n";
        echo "Raw PHP result: {$rawResult}\n";
        echo "Pipeline result: {$pipelineResult}\n";
        echo "ResultChain result: {$chainResult}\n";
        echo "All implementations produce identical results: " . ($pipelineResult === $rawResult && $chainResult === $rawResult ? 'YES' : 'NO') . "\n";
    });
});