<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Tests\Benchmarks;

use Cognesy\Pipeline\Pipeline;
use Cognesy\Utils\Chain\ResultChain;

require_once __DIR__ . '/TestMiddleware.php';

/**
 * PHPBench benchmarks for Pipeline performance testing
 * 
 * Mirrors the functionality from PipelinePerformanceTest.php but using PHPBench
 * for more accurate statistical measurements.
 * 
 * @BeforeMethods({"setUp", "resetMiddleware"})
 * @AfterMethods({"tearDown"})
 * @Revs(1000)
 * @Iterations(5)
 * @Warmup(2)
 */
class PipelineBench
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
        // Clean up if needed
        gc_collect_cycles();
    }

    /**
     * @Subject
     * @Groups({"pipeline", "simple"})
     */
    public function benchSimplePipeline(): void
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
     * @Groups({"pipeline", "middleware"})
     */
    public function benchMiddlewarePipeline(): void
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
     * @Groups({"pipeline", "hooks"})
     */
    public function benchHooksPipeline(): void
    {
        Pipeline::for($this->testText)
            ->beforeEach(fn($comp) => BenchDummyLogger::log("Before: processing " . substr(json_encode($comp->result()->unwrap()), 0, 20)))
            ->afterEach(fn($comp) => BenchDummyLogger::log("After: processed " . substr(json_encode($comp->result()->unwrap()), 0, 20)))
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
     * @Groups({"pipeline", "middleware", "hooks", "full"})
     */
    public function benchMiddlewareAndHooksPipeline(): void
    {
        Pipeline::for($this->testText)
            ->withMiddleware(
                new BenchTimerMiddleware(),
                new BenchRetryMiddleware(),
                new BenchErrorLoggerMiddleware(),
            )
            ->beforeEach(fn($comp) => BenchDummyLogger::log("Before: processing " . substr(json_encode($comp->result()->unwrap()), 0, 20)))
            ->afterEach(fn($comp) => BenchDummyLogger::log("After: processed " . substr(json_encode($comp->result()->unwrap()), 0, 20)))
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
     * @Groups({"baseline", "raw"})
     */
    public function benchRawPhp(): void
    {
        $words = explode(' ', $this->testText);
        $words = array_map('trim', $words);
        $words = array_filter($words, fn($word) => strlen($word) >= 3);
        $words = array_map('strtolower', $words);
        implode(' ', $words);
    }

    /**
     * @Subject
     * @Groups({"baseline", "resultchain"})
     */
    public function benchResultChain(): void
    {
        ResultChain::for($this->testText)
            ->through(fn($text) => explode(' ', $text))
            ->through(fn($words) => array_map('trim', $words))
            ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
            ->through(fn($words) => array_map('strtolower', $words))
            ->then(fn($result) => implode(' ', $result->unwrap()))
            ->process();
    }
}