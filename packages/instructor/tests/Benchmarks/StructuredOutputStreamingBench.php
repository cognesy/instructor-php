<?php declare(strict_types=1);

/**
 * Streaming Driver Performance Benchmark
 *
 * Compares performance of three streaming implementations:
 *
 * 1. CLEAN - Transducer-based pipeline with minimal memory footprint
 *    - Uses functional composition and immutable data structures
 *    - Constant O(1) memory overhead per stream
 *    - Single source of truth for content accumulation (buffer-only)
 *    - New default as of v1.12.0
 *    - Architecture documented in: packages/instructor/STREAMING.md
 *
 * 2. LEGACY - Original implementation using stateful reducers
 *    - Older architecture maintained for compatibility
 *    - More memory accumulation with dual content tracking
 *    - Will be deprecated in future versions
 *
 * 3. PARTIALS - Partial JSON extraction streaming
 *    - Intermediate implementation between Legacy and Clean
 *    - Different accumulation strategy than Clean
 *    - Uses partial JSON parsing but not transducers
 *
 * Each driver is tested in:
 * - STREAMING mode: Process 1KB response as ~30 SSE chunks (realistic simulation)
 * - SYNC mode: Process complete 1KB response at once (baseline comparison)
 *
 * ## How to Run
 *
 * All benchmarks:
 *   composer bench
 *
 * All streaming driver benchmarks:
 *   composer bench -- --filter=StructuredOutputStreamingBench
 *
 * Specific driver streaming:
 *   composer bench -- --filter=benchCleanStream1KB
 *   composer bench -- --filter=benchLegacyStream1KB
 *   composer bench -- --filter=benchPartialsStream1KB
 *
 * Specific driver sync:
 *   composer bench -- --filter=benchCleanSync1KB
 *   composer bench -- --filter=benchLegacySync1KB
 *   composer bench -- --filter=benchPartialsSync1KB
 *
 * Compare all streaming drivers:
 *   composer bench -- --filter="benchCleanStream1KB|benchLegacyStream1KB|benchPartialsStream1KB"
 *
 * With memory profiling:
 *   composer bench -- --filter=StructuredOutputStreamingBench --profile
 *
 * ## Expected Results
 *
 * Time (lower is better):
 * - Clean should be fastest in streaming (10-30% faster than Legacy)
 * - Sync modes should be similar across all drivers (< 5% difference)
 * - DecoratedPipeline should be between Clean and Legacy
 *
 * Memory (lower is better):
 * - Clean: Lowest memory usage (~constant overhead)
 * - DecoratedPipeline: Medium memory usage
 * - Legacy: Highest memory usage (accumulates more state)
 *
 * ## Interpreting Results
 *
 * PHPBench output format:
 *   benchmark          subject              revs  iter  mem_peak  time_avg  time_dev
 *   ----------------------------------------------------------------------------------
 *   CleanStream1KB     benchCleanStream1KB  200   5    2.5mb     0.5ms     ±0.1%
 *
 * - revs: Number of times operation repeats per iteration
 * - iter: Number of iterations (5 in this benchmark)
 * - mem_peak: Peak memory usage
 * - time_avg: Average time per operation
 * - time_dev: Standard deviation (lower = more consistent)
 *
 * Look for:
 * 1. Clean should have lowest time_avg in streaming benchmarks
 * 2. Clean should have lowest mem_peak across all benchmarks
 * 3. All drivers should have similar performance in sync mode
 *
 * ## Benchmark Summary
 *
 * | Method                     | Driver   | Mode      | Purpose                    |
 * |----------------------------|----------|-----------|----------------------------|
 * | benchCleanStream1KB        | Clean    | Streaming | Test Clean streaming perf  |
 * | benchLegacyStream1KB       | Legacy   | Streaming | Test Legacy streaming perf |
 * | benchPartialsStream1KB     | DecoratedPipeline | Streaming | Test DecoratedPipeline stream perf  |
 * | benchCleanSync1KB          | Clean    | Sync      | Baseline for Clean         |
 * | benchLegacySync1KB         | Legacy   | Sync      | Baseline for Legacy        |
 * | benchPartialsSync1KB       | DecoratedPipeline | Sync      | Baseline for DecoratedPipeline      |
 */

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

require_once __DIR__ . '/../Support/FakeInferenceDriver.php';

final class StructuredOutputStreamingBench
{
    /**
     * Generate 1KB stream split into multiple SSE chunks.
     * Simulates realistic LLM streaming response.
     */
    private function make1KBStream(): array
    {
        $open = '{"list":[';
        $close = ']}';
        $items = [];
        $size = strlen($open) + strlen($close);
        $i = 0;
        while ($size < 1024) {
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

    /**
     * Generate 1KB JSON response as complete string.
     * Used for sync mode baseline testing.
     */
    private function make1KBJson(): string
    {
        $content = '{"list":[';
        $i = 0;
        while (strlen($content) < 1024) {
            $txt = str_repeat('x', 16);
            $piece = sprintf('%s{"i":%d,"t":"%s"}', $i === 0 ? '' : ',', $i, $txt);
            $content .= $piece;
            $i++;
        }
        return $content . ']}';
    }

    private function responseModelForSequence(): mixed
    {
        // Use Sequence<stdClass> to avoid extra class declarations in this file
        return new Sequence(\stdClass::class);
    }

    // ============================================================================
    // STREAMING BENCHMARKS (1KB response)
    // ============================================================================

    /**
     * Clean driver - transducer-based streaming with low memory footprint
     *
     * @Revs(200)
     * @Iterations(5)
     * @Warmup(2)
     */
    public function benchCleanStream1KB(): void
    {
        $driver = new FakeInferenceDriver(streamBatches: [ $this->make1KBStream() ]);

        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->withConfig((new StructuredOutputConfig())->with(responseIterator: 'modular'))
            ->with(
                messages: 'Emit sequence',
                responseModel: $this->responseModelForSequence(),
                mode: OutputMode::Json,
            );

        $stream = $so->stream();
        $result = $stream->finalValue();
    }

    /**
     * Legacy driver - original streaming implementation
     *
     * @Revs(200)
     * @Iterations(5)
     * @Warmup(2)
     */
    public function benchLegacyStream1KB(): void
    {
        $driver = new FakeInferenceDriver(streamBatches: [ $this->make1KBStream() ]);

        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->withConfig((new StructuredOutputConfig())->with(responseIterator: 'legacy'))
            ->with(
                messages: 'Emit sequence',
                responseModel: $this->responseModelForSequence(),
                mode: OutputMode::Json,
            );

        $stream = $so->stream();
        $result = $stream->finalValue();
    }

    /**
     * DecoratedPipeline driver - partial JSON extraction streaming
     *
     * @Revs(200)
     * @Iterations(5)
     * @Warmup(2)
     */
    public function benchPartialsStream1KB(): void
    {
        $driver = new FakeInferenceDriver(streamBatches: [ $this->make1KBStream() ]);

        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->withConfig((new StructuredOutputConfig())->with(responseIterator: 'partials'))
            ->with(
                messages: 'Emit sequence',
                responseModel: $this->responseModelForSequence(),
                mode: OutputMode::Json,
            );

        $stream = $so->stream();
        $result = $stream->finalValue();
    }

    // ============================================================================
    // SYNC BENCHMARKS (1KB response) - baseline for comparison
    // ============================================================================

    /**
     * Sync mode with Clean driver config (no actual streaming)
     *
     * @Revs(200)
     * @Iterations(5)
     * @Warmup(2)
     */
    public function benchCleanSync1KB(): void
    {
        $json = $this->make1KBJson();
        $driver = new FakeInferenceDriver(responses: [ new InferenceResponse(content: $json) ]);

        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->withConfig((new StructuredOutputConfig())->with(responseIterator: 'modular'))
            ->with(
                messages: 'Emit sequence',
                responseModel: $this->responseModelForSequence(),
                mode: OutputMode::Json,
            );

        $result = $so->get();
    }

    /**
     * Sync mode with Legacy driver config (no actual streaming)
     *
     * @Revs(200)
     * @Iterations(5)
     * @Warmup(2)
     */
    public function benchLegacySync1KB(): void
    {
        $json = $this->make1KBJson();
        $driver = new FakeInferenceDriver(responses: [ new InferenceResponse(content: $json) ]);

        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->withConfig((new StructuredOutputConfig())->with(responseIterator: 'legacy'))
            ->with(
                messages: 'Emit sequence',
                responseModel: $this->responseModelForSequence(),
                mode: OutputMode::Json,
            );

        $result = $so->get();
    }

    /**
     * Sync mode with DecoratedPipeline driver config (no actual streaming)
     *
     * @Revs(200)
     * @Iterations(5)
     * @Warmup(2)
     */
    public function benchPartialsSync1KB(): void
    {
        $json = $this->make1KBJson();
        $driver = new FakeInferenceDriver(responses: [ new InferenceResponse(content: $json) ]);

        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->withConfig((new StructuredOutputConfig())->with(responseIterator: 'partials'))
            ->with(
                messages: 'Emit sequence',
                responseModel: $this->responseModelForSequence(),
                mode: OutputMode::Json,
            );

        $result = $so->get();
    }
}
