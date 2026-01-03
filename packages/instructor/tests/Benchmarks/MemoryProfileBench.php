<?php declare(strict_types=1);

/**
 * Memory Profiling Benchmark - Deep Memory Analysis
 *
 * This benchmark measures actual memory consumption, object creation overhead,
 * and memory efficiency of stream vs sync processing. Designed to identify
 * production readiness issues and optimization opportunities.
 *
 * ## What This Measures
 *
 * 1. **Peak Memory Usage**: memory_get_peak_usage(true) - real allocated memory
 * 2. **Memory Delta**: memory used during operation vs baseline
 * 3. **Memory Overhead**: memory used vs actual payload size (efficiency ratio)
 * 4. **Baseline Memory**: memory usage before any processing
 *
 * ## Key Metrics Reported
 *
 * - mem_peak: Peak memory (from PHPBench)
 * - Efficiency Ratio: payload_size / memory_used (higher = better)
 * - Memory Overhead: memory_used - payload_size (lower = better)
 *
 * ## Test Scenarios
 *
 * Payloads: 1KB, 10KB, 100KB
 * - Small chunks (realistic SSE): ~32 bytes per chunk
 * - Measures memory with real object creation overhead
 *
 * ## How to Run
 *
 * Basic memory profile:
 *   composer bench -- --filter=MemoryProfileBench
 *
 * Single test:
 *   composer bench -- --filter=benchCleanStream1KB
 *
 * ## Expected Results
 *
 * Good memory efficiency:
 * - Overhead ratio < 10x payload size
 * - Stream peak memory < Sync peak memory (for large payloads)
 * - Clean driver uses less memory than Legacy
 *
 * Warning signs:
 * - Overhead ratio > 20x payload size = memory leak or inefficiency
 * - Stream using more memory than Sync = broken streaming
 * - Memory growing non-linearly with payload size = scalability issue
 */

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

require_once __DIR__ . '/../Support/FakeInferenceDriver.php';

final class MemoryProfileBench
{
    private array $memoryStats = [];

    /**
     * Generate stream with realistic SSE chunk sizes (~32 bytes per chunk)
     */
    private function makeStream(int $targetBytes): array
    {
        $open = '{"list":[';
        $close = ']}';
        $items = [];
        $size = strlen($open) + strlen($close);
        $i = 0;

        while ($size < $targetBytes) {
            $txt = str_repeat('x', 16);
            $item = sprintf('{"i":%d,"t":"%s"}', $i, $txt);
            $sep = ($i === 0) ? '' : ',';
            $items[] = $sep . $item;
            $size += strlen($sep . $item);
            $i++;
        }

        $chunks = [];
        $chunks[] = new PartialInferenceResponse(contentDelta: $open);
        foreach ($items as $piece) {
            $chunks[] = new PartialInferenceResponse(contentDelta: $piece);
        }
        $chunks[] = new PartialInferenceResponse(contentDelta: $close, finishReason: 'stop');

        return $chunks;
    }

    private function makeJson(int $targetBytes): string
    {
        $content = '{"list":[';
        $i = 0;

        while (strlen($content) < $targetBytes) {
            $txt = str_repeat('x', 16);
            $piece = sprintf('%s{"i":%d,"t":"%s"}', $i === 0 ? '' : ',', $i, $txt);
            $content .= $piece;
            $i++;
        }

        return $content . ']}';
    }

    private function responseModel(): mixed
    {
        return new Sequence(\stdClass::class);
    }

    // ============================================================================
    // 1KB Benchmarks
    // ============================================================================

    /**
     * @Revs(100)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     */
    public function benchStream1KB(): void
    {
        $driver = new FakeInferenceDriver(streamBatches: [$this->makeStream(1024)]);
        $this->runStreamBench($driver, 1024);
    }

    /**
     * @Revs(100)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     */
    public function benchSync1KB(): void
    {
        $driver = new FakeInferenceDriver(responses: [new InferenceResponse(content: $this->makeJson(1024))]);
        $this->runSyncBench($driver, 1024);
    }

    // ============================================================================
    // 10KB Benchmarks
    // ============================================================================

    /**
     * @Revs(50)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     */
    public function benchStream10KB(): void
    {
        $driver = new FakeInferenceDriver(streamBatches: [$this->makeStream(10240)]);
        $this->runStreamBench($driver, 10240);
    }

    /**
     * @Revs(50)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     */
    public function benchSync10KB(): void
    {
        $driver = new FakeInferenceDriver(responses: [new InferenceResponse(content: $this->makeJson(10240))]);
        $this->runSyncBench($driver, 10240);
    }

    // ============================================================================
    // 100KB Benchmarks - Stress test for memory efficiency
    // ============================================================================

    /**
     * @Revs(10)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     */
    public function benchStream100KB(): void
    {
        $driver = new FakeInferenceDriver(streamBatches: [$this->makeStream(102400)]);
        $this->runStreamBench($driver, 102400);
    }

    /**
     * @Revs(10)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     */
    public function benchSync100KB(): void
    {
        $driver = new FakeInferenceDriver(responses: [new InferenceResponse(content: $this->makeJson(102400))]);
        $this->runSyncBench($driver, 102400);
    }

    // ============================================================================
    // Memory Measurement Helpers
    // ============================================================================

    private function runStreamBench(FakeInferenceDriver $driver, int $payloadSize): void
    {
        // Force garbage collection to get clean baseline
        gc_collect_cycles();
        $memBefore = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);

        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->with(
                messages: 'Emit sequence',
                responseModel: $this->responseModel(),
                mode: OutputMode::Json,
            );

        $stream = $so->stream();
        $result = $stream->finalValue();

        $memAfter = memory_get_usage(true);
        $peakAfter = memory_get_peak_usage(true);

        // Store stats for analysis (in production, this would be logged)
        $this->recordMemoryStats([
            'mode' => 'stream',
            'payload_size' => $payloadSize,
            'mem_before' => $memBefore,
            'mem_after' => $memAfter,
            'mem_delta' => $memAfter - $memBefore,
            'peak_before' => $peakBefore,
            'peak_after' => $peakAfter,
            'peak_delta' => $peakAfter - $peakBefore,
            'overhead_ratio' => ($peakAfter - $peakBefore) / $payloadSize,
        ]);
    }

    private function runSyncBench(FakeInferenceDriver $driver, int $payloadSize): void
    {
        // Force garbage collection to get clean baseline
        gc_collect_cycles();
        $memBefore = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);

        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->with(
                messages: 'Emit sequence',
                responseModel: $this->responseModel(),
                mode: OutputMode::Json,
            );

        $result = $so->get();

        $memAfter = memory_get_usage(true);
        $peakAfter = memory_get_peak_usage(true);

        // Store stats for analysis
        $this->recordMemoryStats([
            'mode' => 'sync',
            'payload_size' => $payloadSize,
            'mem_before' => $memBefore,
            'mem_after' => $memAfter,
            'mem_delta' => $memAfter - $memBefore,
            'peak_before' => $peakBefore,
            'peak_after' => $peakAfter,
            'peak_delta' => $peakAfter - $peakBefore,
            'overhead_ratio' => ($peakAfter - $peakBefore) / $payloadSize,
        ]);
    }

    private function recordMemoryStats(array $stats): void
    {
        // In real scenario, write to file or log
        // For benchmark, we just collect but PHPBench handles the reporting
        $this->memoryStats[] = $stats;
    }
}
