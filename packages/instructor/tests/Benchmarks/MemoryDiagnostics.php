<?php declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Benchmarks;

use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceRequestDriver;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

/**
 * Memory Diagnostics - Lean memory profiling tool
 *
 * Quick diagnostics for memory consumption issues.
 * Run directly: php packages/instructor/tests/Benchmarks/MemoryDiagnostics.php
 *
 * Usage:
 *   php MemoryDiagnostics.php              # All tests
 *   php MemoryDiagnostics.php sync-stream  # Sync vs Stream only
 *   php MemoryDiagnostics.php layers       # Layer isolation only
 *   php MemoryDiagnostics.php pipeline     # Pipeline checkpoints
 */
final class MemoryDiagnostics
{
    private const PAYLOAD_SIZE = 10240; // 10KB

    public static function main(string $test = 'all'): void
    {
        echo "\n" . str_repeat('═', 70) . "\n";
        echo " MEMORY DIAGNOSTICS - Instructor Streaming\n";
        echo str_repeat('═', 70) . "\n\n";

        $tests = match($test) {
            'sync-stream', 'compare' => ['syncVsStream'],
            'layers', 'layer' => ['layerIsolation'],
            'pipeline', 'checkpoints' => ['pipelineCheckpoints'],
            'streaming', 'stream-flow' => ['realStreamingFlow'],
            'growth', 'pattern' => ['memoryGrowthPattern'],
            'invariants' => ['streamingInvariants'],
            default => ['syncVsStream', 'layerIsolation', 'pipelineCheckpoints', 'realStreamingFlow', 'memoryGrowthPattern', 'streamingInvariants'],
        };

        foreach ($tests as $testName) {
            self::$testName();
        }

        echo "\n" . str_repeat('═', 70) . "\n";
        echo " ✓ Diagnostics complete\n";
        echo str_repeat('═', 70) . "\n\n";
    }

    // TEST 1: Sync vs Stream Memory Comparison
    private static function syncVsStream(): void
    {
        echo "TEST 1: Sync vs Stream Memory Comparison\n";
        echo str_repeat('─', 70) . "\n\n";

        // Run sync test in isolation
        [$syncPeak, $syncObjects, $syncBaseline] = self::runSyncTest();
        gc_collect_cycles();

        // Run stream test in isolation
        [$streamPeak, $streamObjects, $streamBaseline] = self::runStreamTest();

        // Report
        printf("  Sync Memory:\n");
        printf("    Baseline:  %s\n", self::formatBytes($syncBaseline));
        printf("    Peak:      %s\n", self::formatBytes($syncPeak));
        printf("    Delta:     %s\n", self::formatBytes($syncPeak - $syncBaseline));
        printf("    Objects:   %d allocated\n", $syncObjects);

        printf("\n  Stream Memory:\n");
        printf("    Baseline:  %s\n", self::formatBytes($streamBaseline));
        printf("    Peak:      %s\n", self::formatBytes($streamPeak));
        printf("    Delta:     %s\n", self::formatBytes($streamPeak - $streamBaseline));
        printf("    Objects:   %d allocated\n", $streamObjects);

        $diff = $streamPeak - $syncPeak;
        $pct = $syncPeak > 0 ? ($diff / $syncPeak) * 100 : 0;

        printf("\n  Peak Difference: %s (%+.1f%%)\n\n", self::formatBytes(abs($diff)), $pct);

        if (abs($pct) < 10) {
            echo "  ✅ Stream and Sync use similar peak memory\n";
        } elseif ($diff > 0) {
            echo "  ⚠️  Stream uses MORE peak memory than Sync\n";
        } else {
            echo "  ✅ Stream uses LESS peak memory than Sync\n";
        }

        echo "\n";
    }

    private static function runSyncTest(): array
    {
        gc_collect_cycles();
        memory_reset_peak_usage();
        $baseline = memory_get_usage(false);
        $gcBefore = gc_status();

        $json = self::makeJson(self::PAYLOAD_SIZE);
        $driver = new FakeInferenceRequestDriver(responses: [new InferenceResponse(content: $json)]);

        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->withConfig(new StructuredOutputConfig())
            ->with(messages: 'Test', responseModel: new Sequence(\stdClass::class), mode: OutputMode::Json);

        $result = $so->get();
        $peak = memory_get_peak_usage(false);
        $gcAfter = gc_status();
        $objects = $gcAfter['roots'] - $gcBefore['roots'];

        unset($so, $result, $driver, $json);

        return [$peak, $objects, $baseline];
    }

    private static function runStreamTest(): array
    {
        gc_collect_cycles();
        memory_reset_peak_usage();
        $baseline = memory_get_usage(false);
        $gcBefore = gc_status();

        $chunks = self::makeStream(self::PAYLOAD_SIZE);
        $driver = new FakeInferenceRequestDriver(streamBatches: [$chunks]);

        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->withConfig(new StructuredOutputConfig())
            ->with(messages: 'Test', responseModel: new Sequence(\stdClass::class), mode: OutputMode::Json);

        $stream = $so->stream();
        $result = $stream->finalValue();
        $peak = memory_get_peak_usage(false);
        $gcAfter = gc_status();
        $objects = $gcAfter['roots'] - $gcBefore['roots'];

        unset($so, $result, $stream, $driver, $chunks);

        return [$peak, $objects, $baseline];
    }

    // TEST 2: Layer Isolation
    private static function layerIsolation(): void
    {
        echo "TEST 2: Layer Isolation (Which component uses memory?)\n";
        echo str_repeat('─', 70) . "\n\n";

        $chunks = self::makeStream(self::PAYLOAD_SIZE);

        // Layer 1: Chunks
        gc_collect_cycles();
        $before = memory_get_usage(false);
        $testChunks = self::makeStream(self::PAYLOAD_SIZE);
        $after = memory_get_usage(false);
        $layer1 = $after - $before;
        printf("  Layer 1 (Chunks):          %s\n", self::formatBytes($layer1));

        // Layer 2: Driver
        gc_collect_cycles();
        $before = memory_get_usage(false);
        $driver = new FakeInferenceRequestDriver(streamBatches: [$chunks]);
        $after = memory_get_usage(false);
        $layer2 = $after - $before;
        printf("  Layer 2 (Driver):          %s\n", self::formatBytes($layer2));

        // Layer 3: Iteration
        $driver = new FakeInferenceRequestDriver(streamBatches: [self::makeStream(self::PAYLOAD_SIZE)]);
        gc_collect_cycles();
        gc_disable(); // Prevent GC during measurement
        $before = memory_get_usage(false);
        $buffer = '';
        $request = new \Cognesy\Polyglot\Inference\Data\InferenceRequest();
        foreach ($driver->makeStreamResponsesFor($request) as $partial) {
            $buffer .= $partial->contentDelta;
        }
        $after = memory_get_usage(false);
        gc_enable();
        $layer3 = max(0, $after - $before); // Handle negative deltas from GC
        printf("  Layer 3 (Iteration):       %s\n", self::formatBytes($layer3));

        // Layer 4: Full pipeline
        $driver = new FakeInferenceRequestDriver(streamBatches: [self::makeStream(self::PAYLOAD_SIZE)]);
        gc_collect_cycles();
        gc_disable(); // Prevent GC during measurement
        $before = memory_get_usage(false);

        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->withConfig(new StructuredOutputConfig())
            ->with(messages: 'Test', responseModel: new Sequence(\stdClass::class), mode: OutputMode::Json);

        $stream = $so->stream();
        $result = $stream->finalValue();
        $after = memory_get_usage(false);
        gc_enable();
        $layer4 = max(0, $after - $before); // Handle negative deltas from GC
        printf("  Layer 4 (Full Pipeline):   %s\n\n", self::formatBytes($layer4));

        // Analysis
        $instructorOverhead = max(0, $layer4 - $layer3);
        $pct = $layer4 > 0 ? ($instructorOverhead / $layer4) * 100 : 0;
        printf("  Instructor overhead:       %s (%.0f%%)\n\n",
            self::formatBytes(abs($instructorOverhead)),
            $pct
        );

        if ($instructorOverhead > $layer3 * 2) {
            echo "  ⚠️  Overhead primarily in Instructor module\n";
        } elseif ($layer3 > $layer2 * 2) {
            echo "  ⚠️  Overhead in stream iteration\n";
        } else {
            echo "  ✅ Memory distributed across layers\n";
        }

        echo "\n";
    }

    // TEST 3: Pipeline Checkpoints
    private static function pipelineCheckpoints(): void
    {
        echo "TEST 3: Pipeline Memory Checkpoints\n";
        echo str_repeat('─', 70) . "\n\n";

        gc_collect_cycles();
        $baseline = memory_get_usage(false);
        echo "  Baseline:                  " . self::formatBytes($baseline) . "\n";

        $chunks = self::makeStream(self::PAYLOAD_SIZE);
        gc_collect_cycles();
        echo "  After chunks:              " . self::formatDelta($baseline) . "\n";

        $driver = new FakeInferenceRequestDriver(streamBatches: [$chunks]);
        gc_collect_cycles();
        echo "  After driver:              " . self::formatDelta($baseline) . "\n";

        $so = new StructuredOutput();
        gc_collect_cycles();
        echo "  After StructuredOutput:    " . self::formatDelta($baseline) . "\n";

        $so = $so->withDriver($driver);
        gc_collect_cycles();
        echo "  After withDriver():        " . self::formatDelta($baseline) . "\n";

        $so = $so->withConfig(new StructuredOutputConfig());
        gc_collect_cycles();
        echo "  After withConfig():        " . self::formatDelta($baseline) . "\n";

        $so = $so->with(messages: 'Test', responseModel: new Sequence(\stdClass::class), mode: OutputMode::Json);
        gc_collect_cycles();
        echo "  After with():              " . self::formatDelta($baseline) . "\n";

        $stream = $so->stream();
        gc_collect_cycles();
        echo "  After stream():            " . self::formatDelta($baseline) . "\n";

        $result = $stream->finalValue();
        gc_collect_cycles();
        $final = memory_get_usage(false);
        echo "  After finalValue():        " . self::formatDelta($baseline) . "\n";

        printf("\n  Total:                     %s\n", self::formatBytes($final - $baseline));
        printf("  Peak:                      %s\n", self::formatBytes(memory_get_peak_usage(false)));

        echo "\n";
    }

    // TEST 4: Real Streaming Flow
    private static function realStreamingFlow(): void
    {
        echo "TEST 4: Real Streaming Flow (HTTP → Inference)\n";
        echo str_repeat('─', 70) . "\n\n";

        // Create chunks that simulate SSE events
        $sseEvents = self::makeSSEEvents(self::PAYLOAD_SIZE);

        // Use actual streaming HttpResponse mock
        $httpResponse = MockHttpResponseFactory::streaming(
            headers: ['content-type' => 'text/event-stream'],
            chunks: $sseEvents,
        );

        gc_collect_cycles();
        $baseline = memory_get_usage(false);
        $gcBefore = gc_status();

        // This is the ACTUAL production code path:
        // BaseInferenceDriver::httpResponseToInferenceStream()
        $responseData = $httpResponse;

        $partialCount = 0;
        $bodyGrowth = [];
        foreach ($responseData->stream() as $chunk) {
            // Simulate what BaseInferenceDriver does
            $partial = new PartialInferenceResponse(
                contentDelta: $chunk,
                responseData: $responseData, // ← Must be same reference
            );

            // Track body accumulation
            if ($partialCount % 100 === 0) {
                $bodyGrowth[] = strlen($partial->responseData->body());
            }

            $partialCount++;
        }

        $peak = memory_get_peak_usage(false);
        $gcAfter = gc_status();
        $objects = $gcAfter['roots'] - $gcBefore['roots'];

        printf("  Chunks processed:          %d\n", $partialCount);
        printf("  Objects allocated:         %d\n", $objects);
        printf("  Peak memory:               %s\n", self::formatBytes($peak - $baseline));
        printf("  Body growth samples:       %d\n", count($bodyGrowth));

        // Verify BufferedStream is working
        $finalBody = $responseData->body();
        $streamCompleted = $responseData->isStreaming() === false;

        printf("  Final body length:         %d bytes\n", strlen($finalBody));
        printf("  Stream completed:          %s\n", $streamCompleted ? 'yes' : 'NO');

        // Regression checks
        $objectsPerChunk = $objects / max(1, $partialCount);
        $memoryPerChunk = ($peak - $baseline) / max(1, $partialCount);

        printf("\n  Objects per chunk:         %.2f\n", $objectsPerChunk);
        printf("  Memory per chunk:          %s\n", self::formatBytes($memoryPerChunk));

        // Body should grow linearly
        if (count($bodyGrowth) > 2) {
            $firstSample = $bodyGrowth[0];
            $lastSample = end($bodyGrowth);
            $actualGrowth = $lastSample / max(1, $firstSample);

            printf("  Body growth linearity:     %.2fx\n", $actualGrowth);

            if ($actualGrowth < count($bodyGrowth) * 1.2) {
                echo "  ✅ Body accumulation is linear\n";
            } else {
                echo "  ❌ Body growth is non-linear - BufferedStream issue?\n";
            }
        }

        // Future regression thresholds
        if ($objectsPerChunk < 5.0) {
            echo "  ✅ Low object allocation per chunk\n";
        } else {
            echo "  ⚠️  High object allocation - potential regression\n";
        }

        if ($memoryPerChunk < 1024) { // 1KB per chunk
            echo "  ✅ Low memory per chunk\n";
        } else {
            echo "  ⚠️  High memory per chunk - potential leak\n";
        }

        echo "\n";
    }

    // TEST 5: Memory Growth Pattern Detection
    private static function memoryGrowthPattern(): void
    {
        echo "TEST 5: Memory Growth Pattern (O(n) vs O(n²) detection)\n";
        echo str_repeat('─', 70) . "\n\n";

        $testSizes = [1024, 5120, 10240]; // 1KB, 5KB, 10KB
        $results = [];

        foreach ($testSizes as $size) {
            gc_collect_cycles();
            memory_reset_peak_usage();
            $before = memory_get_usage(false);

            $chunks = self::makeStream($size);
            $driver = new FakeInferenceRequestDriver(streamBatches: [$chunks]);

            $so = (new StructuredOutput)
                ->withDriver($driver)
                ->withConfig(new StructuredOutputConfig())
                ->with(messages: 'Test', responseModel: new Sequence(\stdClass::class), mode: OutputMode::Json);

            $stream = $so->stream();
            $result = $stream->finalValue();

            $peak = memory_get_peak_usage(false);
            $delta = $peak - $before;

            $results[] = [
                'size' => $size,
                'peak' => $delta,
                'ratio' => $delta / $size,
            ];

            printf("  Payload %6d bytes → Peak: %s (%.2fx overhead)\n",
                $size,
                self::formatBytes($delta),
                $delta / $size
            );

            unset($so, $result, $stream, $driver, $chunks);
        }

        // Analyze growth pattern
        echo "\n  Growth Analysis:\n";

        if (count($results) >= 3) {
            // Compare growth rates
            $firstRatio = $results[0]['ratio'];
            $lastRatio = $results[count($results) - 1]['ratio'];
            $ratioIncrease = $lastRatio / max(0.01, $firstRatio);

            printf("    First ratio:  %.2fx\n", $firstRatio);
            printf("    Last ratio:   %.2fx\n", $lastRatio);
            printf("    Ratio change: %.2fx\n", $ratioIncrease);

            // Linear growth: ratio stays roughly constant
            // O(n²) growth: ratio increases with payload size
            if ($ratioIncrease < 1.5) {
                echo "    ✅ Linear O(n) memory growth\n";
            } else if ($ratioIncrease < 3.0) {
                echo "    ⚠️  Super-linear growth detected\n";
            } else {
                echo "    ❌ O(n²) or worse - REGRESSION DETECTED!\n";
            }

            // Set future baseline
            $avgRatio = array_sum(array_column($results, 'ratio')) / count($results);
            printf("\n    Baseline overhead ratio: %.2fx\n", $avgRatio);
            printf("    Future threshold: %.2fx (warning if exceeded)\n", $avgRatio * 1.5);
        }

        echo "\n";
    }

    // TEST 6: Streaming Invariants Check
    private static function streamingInvariants(): void
    {
        echo "TEST 6: Streaming Invariants (Critical Guarantees)\n";
        echo str_repeat('─', 70) . "\n\n";

        // Measure sync peak first
        gc_collect_cycles();
        memory_reset_peak_usage();
        $json = self::makeJson(self::PAYLOAD_SIZE);
        $syncDriver = new FakeInferenceRequestDriver(responses: [new InferenceResponse(content: $json)]);

        $soSync = (new StructuredOutput)
            ->withDriver($syncDriver)
            ->withConfig(new StructuredOutputConfig())
            ->with(messages: 'Test', responseModel: new Sequence(\stdClass::class), mode: OutputMode::Json);

        $syncResult = $soSync->get();
        $syncPeak = memory_get_peak_usage(false);
        unset($soSync, $syncResult, $syncDriver, $json);

        // Measure stream
        gc_collect_cycles();
        memory_reset_peak_usage();
        $chunks = self::makeStream(self::PAYLOAD_SIZE);
        $driver = new FakeInferenceRequestDriver(streamBatches: [$chunks]);

        // Invariant 1: Stream peak ≤ Sync peak (for same payload)
        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->withConfig(new StructuredOutputConfig())
            ->with(messages: 'Test', responseModel: new Sequence(\stdClass::class), mode: OutputMode::Json);

        $stream = $so->stream();
        $partialCount = 0;
        $firstPartial = null;
        $lastPartial = null;

        foreach ($stream->responses() as $partial) {
            if ($firstPartial === null) {
                $firstPartial = $partial;
            }
            $lastPartial = $partial;
            $partialCount++;
        }

        $streamPeak = memory_get_peak_usage(false);

        echo "  INVARIANT 1: Stream memory ≤ Sync memory\n";
        printf("    Stream peak: %s\n", self::formatBytes($streamPeak));
        printf("    Sync peak:   %s\n", self::formatBytes($syncPeak));

        if ($streamPeak <= $syncPeak * 1.2) { // Allow 20% margin
            echo "    ✅ PASS: Stream uses reasonable memory\n";
        } else {
            echo "    ❌ FAIL: Stream uses MORE memory than sync!\n";
        }

        // Invariant 2: First partial arrives quickly (not after full buffering)
        echo "\n  INVARIANT 2: Progressive delivery\n";
        printf("    Total partials: %d\n", $partialCount);

        if ($partialCount > 10) {
            echo "    ✅ PASS: Multiple partial responses delivered\n";
        } else {
            echo "    ❌ FAIL: Too few partials - buffering entire response?\n";
        }

        // Invariant 3: Body accumulates progressively
        if ($firstPartial && $lastPartial) {
            $firstBody = $firstPartial->content();
            $lastBody = $lastPartial->content();

            echo "\n  INVARIANT 3: Progressive body accumulation\n";
            printf("    First partial body: %d bytes\n", strlen($firstBody));
            printf("    Last partial body:  %d bytes\n", strlen($lastBody));

            if (strlen($lastBody) > strlen($firstBody) * 2) {
                echo "    ✅ PASS: Body grows during streaming\n";
            } else {
                echo "    ⚠️  WARNING: Body not accumulating properly\n";
            }
        }

        // Invariant 4: HttpResponseData sharing documentation
        echo "\n  INVARIANT 4: HttpResponseData sharing\n";
        echo "    Expected: Single HttpResponseData instance across all partials\n";
        echo "    Expected: Single BufferedStream instance\n";
        echo "    Note: Verified manually in Test 4 (Real Streaming Flow)\n";

        echo "\n";
    }

    // Helpers
    private static function formatBytes(int|float $bytes): string {
        $bytes = (int) $bytes;
        if ($bytes >= 1048576) return sprintf('%.2f MB', $bytes / 1048576);
        if ($bytes >= 1024) return sprintf('%.2f KB', $bytes / 1024);
        return sprintf('%d B', $bytes);
    }

    private static function formatDelta(int $baseline): string {
        $current = memory_get_usage(false);
        $delta = $current - $baseline;
        return self::formatBytes(abs($delta));
    }

    private static function makeStream(int $targetBytes): array {
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

    private static function makeJson(int $targetBytes): string {
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

    private static function makeSSEEvents(int $targetBytes): array
    {
        // Simulate Server-Sent Events format
        $events = [];
        $json = self::makeJson($targetBytes);

        // Split into realistic SSE chunks (simulate progressive JSON)
        $chunkSize = 256;
        $length = strlen($json);
        for ($i = 0; $i < $length; $i += $chunkSize) {
            $chunk = substr($json, $i, $chunkSize);
            $events[] = $chunk;
        }

        return $events;
    }
}

// Run if executed directly
if (basename($argv[0] ?? '') === 'MemoryDiagnostics.php') {
    require_once __DIR__ . '/../../../../vendor/autoload.php';
    MemoryDiagnostics::main($argv[1] ?? 'all');
}
