<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Tests\Benchmarks;

use Cognesy\Pipeline\Pipeline;
use Cognesy\Utils\Chain\ResultChain;

require_once __DIR__ . '/TestMiddleware.php';

/**
 * PHPBench memory profiling benchmarks for Pipeline performance testing
 * 
 * Focuses on detailed memory consumption analysis with additional
 * memory measurement points and varying data sizes.
 * 
 * @BeforeMethods({"setUp", "resetMiddleware", "recordBaselineMemory"})
 * @AfterMethods({"tearDown", "recordMemoryDelta"})
 * @Revs(1000)
 * @Iterations(10)
 * @Warmup(3)
 */
class MemoryProfileBench
{
    private string $smallText;
    private string $mediumText;
    private string $largeText;
    private int $baselineMemory;
    private int $peakMemoryDelta;
    
    public function setUp(): void
    {
        // Small text (20 words)
        $this->smallText = 'The quick brown fox jumps over the lazy dog in the forest near the old oak tree today.';
        
        // Medium text (100 words)
        $this->mediumText = str_repeat($this->smallText . ' ', 5);
        
        // Large text (500 words)
        $this->largeText = str_repeat($this->smallText . ' ', 25);
        
        gc_collect_cycles();
    }
    
    public function resetMiddleware(): void
    {
        BenchTimerMiddleware::reset();
        BenchErrorLoggerMiddleware::reset();
        BenchDummyLogger::reset();
    }
    
    public function recordBaselineMemory(): void
    {
        $this->baselineMemory = memory_get_usage(true);
    }
    
    public function tearDown(): void
    {
        gc_collect_cycles();
    }
    
    public function recordMemoryDelta(): void
    {
        $this->peakMemoryDelta = memory_get_peak_usage(true) - $this->baselineMemory;
    }

    // === SMALL TEXT BENCHMARKS ===

    /**
     * @Subject
     * @Groups({"memory", "small", "raw"})
     */
    public function benchSmallRawPhp(): void
    {
        $words = explode(' ', $this->smallText);
        $words = array_map('trim', $words);
        $words = array_filter($words, fn($word) => strlen($word) >= 3);
        $words = array_map('strtolower', $words);
        $result = implode(' ', $words);
        
        // Force memory allocation to be measurable
        $temp = [$result, $words];
        unset($temp);
    }

    /**
     * @Subject
     * @Groups({"memory", "small", "pipeline"})
     */
    public function benchSmallSimplePipeline(): void
    {
        $result = Pipeline::for($this->smallText)
            ->through(fn($text) => explode(' ', $text))
            ->through(fn($words) => array_map('trim', $words))
            ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
            ->through(fn($words) => array_map('strtolower', $words))
            ->finally(fn($result) => implode(' ', $result->unwrap()))
            ->process()
            ->value();
            
        // Ensure result is used
        $temp = $result;
        unset($temp);
    }

    /**
     * @Subject
     * @Groups({"memory", "small", "pipeline", "middleware"})
     */
    public function benchSmallMiddlewarePipeline(): void
    {
        $result = Pipeline::for($this->smallText)
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
            
        $temp = $result;
        unset($temp);
    }

    // === MEDIUM TEXT BENCHMARKS ===

    /**
     * @Subject
     * @Groups({"memory", "medium", "raw"})
     */
    public function benchMediumRawPhp(): void
    {
        $words = explode(' ', $this->mediumText);
        $words = array_map('trim', $words);
        $words = array_filter($words, fn($word) => strlen($word) >= 3);
        $words = array_map('strtolower', $words);
        $result = implode(' ', $words);
        
        $temp = [$result, $words];
        unset($temp);
    }

    /**
     * @Subject
     * @Groups({"memory", "medium", "pipeline"})
     */
    public function benchMediumSimplePipeline(): void
    {
        $result = Pipeline::for($this->mediumText)
            ->through(fn($text) => explode(' ', $text))
            ->through(fn($words) => array_map('trim', $words))
            ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
            ->through(fn($words) => array_map('strtolower', $words))
            ->finally(fn($result) => implode(' ', $result->unwrap()))
            ->process()
            ->value();
            
        $temp = $result;
        unset($temp);
    }

    /**
     * @Subject
     * @Groups({"memory", "medium", "resultchain"})
     */
    public function benchMediumResultChain(): void
    {
        $result = ResultChain::for($this->mediumText)
            ->through(fn($text) => explode(' ', $text))
            ->through(fn($words) => array_map('trim', $words))
            ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
            ->through(fn($words) => array_map('strtolower', $words))
            ->then(fn($result) => implode(' ', $result->unwrap()))
            ->process();
            
        $temp = $result;
        unset($temp);
    }

    // === LARGE TEXT BENCHMARKS ===

    /**
     * @Subject
     * @Groups({"memory", "large", "raw"})
     */
    public function benchLargeRawPhp(): void
    {
        $words = explode(' ', $this->largeText);
        $words = array_map('trim', $words);
        $words = array_filter($words, fn($word) => strlen($word) >= 3);
        $words = array_map('strtolower', $words);
        $result = implode(' ', $words);
        
        $temp = [$result, $words];
        unset($temp);
    }

    /**
     * @Subject
     * @Groups({"memory", "large", "pipeline"})
     */
    public function benchLargeSimplePipeline(): void
    {
        $result = Pipeline::for($this->largeText)
            ->through(fn($text) => explode(' ', $text))
            ->through(fn($words) => array_map('trim', $words))
            ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
            ->through(fn($words) => array_map('strtolower', $words))
            ->finally(fn($result) => implode(' ', $result->unwrap()))
            ->process()
            ->value();
            
        $temp = $result;
        unset($temp);
    }

    /**
     * @Subject
     * @Groups({"memory", "large", "resultchain"})
     */
    public function benchLargeResultChain(): void
    {
        $result = ResultChain::for($this->largeText)
            ->through(fn($text) => explode(' ', $text))
            ->through(fn($words) => array_map('trim', $words))
            ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
            ->through(fn($words) => array_map('strtolower', $words))
            ->then(fn($result) => implode(' ', $result->unwrap()))
            ->process();
            
        $temp = $result;
        unset($temp);
    }

    // === OBJECT ALLOCATION STRESS TESTS ===

    /**
     * @Subject
     * @Groups({"memory", "stress", "pipeline"})
     */
    public function benchObjectAllocationStress(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $result = Pipeline::for($this->smallText)
                ->through(fn($text) => explode(' ', $text))
                ->through(fn($words) => array_map('trim', $words))
                ->through(fn($words) => array_filter($words, fn($word) => strlen($word) >= 3))
                ->through(fn($words) => array_map('strtolower', $words))
                ->finally(fn($result) => implode(' ', $result->unwrap()))
                ->process();
            
            // Create temporary objects to stress memory allocation
            $temp[] = clone $result;
        }
        unset($temp);
    }

    /**
     * @Subject
     * @Groups({"memory", "stress", "raw"})
     */
    public function benchArrayAllocationStress(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $words = explode(' ', $this->smallText);
            $words = array_map('trim', $words);
            $words = array_filter($words, fn($word) => strlen($word) >= 3);
            $words = array_map('strtolower', $words);
            $result = implode(' ', $words);
            
            // Create temporary arrays to stress memory allocation
            $temp[] = [$result, $words, $i];
        }
        unset($temp);
    }
}