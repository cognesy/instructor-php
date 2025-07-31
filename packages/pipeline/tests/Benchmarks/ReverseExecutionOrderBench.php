<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Tests\Benchmarks;

use Cognesy\Pipeline\Pipeline;
use Cognesy\Utils\Chain\ResultChain;

require_once __DIR__ . '/TestMiddleware.php';

/**
 * PHPBench benchmarks for reverse execution order testing
 * 
 * Tests the same functionality as ExecutionOrderBench but in reverse order
 * to measure the impact of execution order on performance.
 * 
 * @BeforeMethods({"setUp", "resetMiddleware"})
 * @AfterMethods({"tearDown"})
 * @Revs(1000)
 * @Iterations(3)
 * @Warmup(1)
 * @OutputMode("time")
 */
class ReverseExecutionOrderBench
{
    private string $testText;
    
    public function setUp(): void
    {
        $this->testText = 'The quick brown fox jumps over the lazy dog. This is a test string for performance measurement with multiple words of varying lengths.';
    }
    
    public function resetMiddleware(): void
    {
        BenchTimerMiddleware::reset();
        BenchErrorLoggerMiddleware::reset();
        BenchDummyLogger::reset();
    }
    
    public function tearDown(): void
    {
        gc_collect_cycles();
    }

    // === REVERSE ORDER TESTS ===

    /**
     * @Subject
     * @Groups({"order", "reverse", "resultchain"})
     */
    public function benchReverseOrder1ResultChain(): void
    {
        ResultChain::for($this->testText)
            ->through(fn($text) => explode(' ', $text))
            ->through(fn($words) => array_map('trim', $words))
            ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
            ->through(fn($words) => array_map('strtolower', $words))
            ->then(fn($result) => implode(' ', $result->unwrap()))
            ->process();
    }

    /**
     * @Subject
     * @Groups({"order", "reverse", "middleware"})
     */
    public function benchReverseOrder2MiddlewarePipeline(): void
    {
        Pipeline::for($this->testText)
            ->withMiddleware(
                new BenchTimerMiddleware(),
                new BenchRetryMiddleware(),
                new BenchErrorLoggerMiddleware(),
            )
            ->through(fn($text) => explode(' ', $text))
            ->through(fn($words) => array_map('trim', $words))
            ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
            ->through(fn($words) => array_map('strtolower', $words))
            ->finally(fn($result) => implode(' ', $result->unwrap()))
            ->process()
            ->value();
    }

    /**
     * @Subject
     * @Groups({"order", "reverse", "pipeline"})
     */
    public function benchReverseOrder3SimplePipeline(): void
    {
        Pipeline::for($this->testText)
            ->through(fn($text) => explode(' ', $text))
            ->through(fn($words) => array_map('trim', $words))
            ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
            ->through(fn($words) => array_map('strtolower', $words))
            ->finally(fn($result) => implode(' ', $result->unwrap()))
            ->process()
            ->value();
    }

    /**
     * @Subject
     * @Groups({"order", "reverse", "baseline"})
     */
    public function benchReverseOrder4RawPhp(): void
    {
        $words = explode(' ', $this->testText);
        $words = array_map('trim', $words);
        $words = array_filter($words, fn($word) => strlen($word) >= 3);
        $words = array_map('strtolower', $words);
        implode(' ', $words);
    }
}